<?php

namespace App\Http\Controllers;

use App\Enums\GamePhase;
use App\Events\RoomChanged;
use App\Game\GameEngine;
use App\Game\GameState;
use App\Http\Requests\RoomSettingsRequest;
use App\Models\Game;
use App\Models\Room;
use App\Rooms\RoomAccess;
use App\Rooms\RoomView;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function __construct(private GameEngine $engine, private RoomAccess $access, private RoomView $view) {}

    private function member(string $name, string $token): array
    {
        return ['name' => $name, 'ready' => false, 'token_hash' => hash('sha256', $token)];
    }

    private function notify(Room $room): void
    {
        try {
            event(new RoomChanged($room->id, $room->version));
        } catch (\Throwable $error) {
            report($error);
        } // HTTP resync remains available during a socket outage.
    }

    private function reply(Room $room, int $index)
    {
        return response()->json($this->view->transform($room, $index))->header('Cache-Control', 'no-store, private');
    }

    public function create(RoomSettingsRequest $request)
    {
        $token = bin2hex(random_bytes(32));
        do {
            $code = strtoupper(Str::random(8));
        } while (Room::where('code', $code)->exists());
        $room = Room::create(['id' => (string) Str::uuid(), 'code' => $code, 'settings' => $request->settings(),
            'members' => [$this->member($request->validated('name'), $token)], 'expires_at' => now()->addDay()]);
        $request->session()->put('room_tokens.'.$room->id, $token);

        return $this->reply($room->refresh(), 0);
    }

    public function join(Request $request)
    {
        $request->merge(['code' => is_string($request->input('code')) ? strtoupper(trim($request->input('code'))) : $request->input('code'), 'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['code' => 'required|string|size:8|alpha_num:ascii', 'name' => 'required|string|min:2|max:20']);
        $token = bin2hex(random_bytes(32));
        [$room, $index, $existing] = DB::transaction(function () use ($request, $data, $token) {
            $room = Room::where('code', $data['code'])->lockForUpdate()->firstOrFail();
            abort_if($room->isClosed(), 410, 'This room is closed.');
            if ($request->session()->has('room_tokens.'.$room->id)) {
                return [$room, $this->access->index($request, $room), true];
            }
            if ($room->status !== 'lobby' || count($room->members) >= $room->settings['capacity']) {
                throw new DomainException('This room has started or is full.');
            }
            foreach ($room->members as $member) {
                if (mb_strtolower($member['name']) === mb_strtolower($data['name'])) {
                    throw new DomainException('That name is already taken in this room.');
                }
            }
            $members = $room->members;
            $members[] = $this->member($data['name'], $token);
            $room->update(['members' => $members, 'version' => $room->version + 1]);

            return [$room, count($members) - 1, false];
        }, 3);
        if (! $existing) {
            $request->session()->put('room_tokens.'.$room->id, $token);
        }
        $this->notify($room);

        return $this->reply($room, $index);
    }

    public function state(Request $request, Room $room)
    {
        $index = $this->access->index($request, $room);
        $changed = false;
        // Any seated player can drive an expired server timer; concurrent polls serialize here.
        $room = DB::transaction(function () use ($room, &$changed) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            if ($room->status === 'playing') {
                $game = Game::whereKey($room->game_uuid)->lockForUpdate()->firstOrFail();
                $state = GameState::fromArray($game->state);
                if ($state->phase === GamePhase::Picking && $state->pickDeadline !== null && time() >= $state->pickDeadline) {
                    $this->engine->revealRound($this->engine->timeout($state));
                    $game->update(['state' => $state->jsonSerialize(), 'status' => $state->phase->value, 'version' => $game->version + 1]);
                    $room->update(['version' => $room->version + 1]);
                    $changed = true;
                }
            }

            return $room;
        }, 3);
        // Recheck after acquiring the latest membership in case a lobby seat left.
        $index = $this->access->index($request, $room);
        if ($changed) {
            $this->notify($room);
        }

        return $this->reply($room, $index);
    }

    public function settings(RoomSettingsRequest $request, Room $room)
    {
        $room = DB::transaction(function () use ($request, $room) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->access->index($request, $room) === $room->host, 403);
            if ($room->status !== 'lobby') {
                throw new DomainException('Settings are locked after kickoff.');
            }
            if ($room->version !== $request->integer('version')) {
                throw new DomainException('The room changed. Please try again.');
            }
            if (count($room->members) > $request->integer('capacity')) {
                throw new DomainException('The room already has more players than that.');
            }
            $members = array_map(fn ($m) => [...$m, 'ready' => false], $room->members);
            $room->update(['settings' => $request->settings(), 'members' => $members, 'host_editing_at' => null, 'version' => $room->version + 1]);

            return $room;
        }, 3);
        $this->notify($room);

        return $this->reply($room, $room->host);
    }

    public function action(Request $request, Room $room, string $action)
    {
        $request->validate(['version' => 'required|integer|min:0', 'cardId' => 'sometimes|string|max:100', 'matchIndex' => 'sometimes|integer|min:0|max:5', 'ready' => 'sometimes|boolean', 'editing' => 'sometimes|boolean']);
        [$room, $index] = DB::transaction(function () use ($request, $room, $action) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $index = $this->access->index($request, $room);
            if ($room->version !== $request->integer('version')) {
                throw new DomainException('The room changed. Please try again.');
            }
            if ($action === 'ready') {
                if ($room->status !== 'lobby') {
                    throw new DomainException('The draft has already started.');
                }
                $members = $room->members;
                $members[$index]['ready'] = $request->boolean('ready');
                $room->members = $members;
            } elseif ($action === 'editing') {
                // Only announces that the host is in the settings screen; nobody loses their seat.
                abort_unless($index === $room->host, 403);
                if ($room->status !== 'lobby') {
                    throw new DomainException('Settings are locked after kickoff.');
                }
                $room->host_editing_at = $request->boolean('editing') ? now() : null;
            } elseif ($action === 'rematch') {
                $this->rematch($room);
            } elseif ($action === 'leave') {
                $this->leave($room, $index);
            } elseif ($action === 'start') {
                abort_unless($index === $room->host, 403);
                if ($room->status !== 'lobby' || count($room->members) !== $room->settings['capacity'] || in_array(false, array_column($room->members, 'ready'), true)) {
                    throw new DomainException('Fill the room and wait for everyone to be ready.');
                }
                $s = $room->settings;
                $state = $this->engine->startGame(array_column($room->members, 'name'), $s['domain'], $s['rounds'], $s['timer'], $s['catalog'] ?? null);
                $this->engine->drawRound($state);
                $game = Game::create(['uuid' => (string) Str::uuid(), 'domain' => $state->domainKey, 'state' => $state->jsonSerialize(), 'status' => $state->phase->value]);
                $room->game_uuid = $game->uuid;
                $room->status = 'playing';
                $room->host_editing_at = null;
            } elseif ($action === 'replay') {
                abort_unless($index === $room->host, 403);
                if ($room->status !== 'finished') {
                    throw new DomainException('Finish the draft first.');
                }
                if (($room->playback['startsAt'] ?? 0) + 15000 > (int) (microtime(true) * 1000)) {
                    throw new DomainException('Wait for this match to finish.');
                }
                $state = GameState::fromArray(Game::findOrFail($room->game_uuid)->state);
                $matches = $state->results['league']['matches'] ?? (isset($state->results['match']) ? [$state->results['match']] : []);
                $match = $request->integer('matchIndex');
                if (! isset($matches[$match])) {
                    throw new DomainException('Choose a football fixture.');
                }
                $room->playback = ['index' => $match, 'startsAt' => (int) (microtime(true) * 1000) + 1500];
            } else {
                if ($room->status !== 'playing') {
                    throw new DomainException('This draft is not running.');
                }
                $game = Game::whereKey($room->game_uuid)->lockForUpdate()->firstOrFail();
                $state = GameState::fromArray($game->state);
                if ($action === 'pick') {
                    // The authenticated seat, never the client-supplied playerIndex.
                    $this->engine->pick($state, $index, $request->string('cardId')->toString());
                    $this->engine->revealRound($state);
                } elseif ($action === 'next' || $action === 'finish') {
                    // Any seated player may advance: the deck and match seed are fixed at kickoff,
                    // so a dropped host can no longer stall the room.
                    if ($action === 'next') {
                        $this->engine->drawRound($this->engine->nextRound($state));
                        $this->pickForDepartedSeat($room, $state);
                    } else {
                        $this->engine->finish($state);
                        $room->status = 'finished';
                        if ($state->domainKey === 'football') {
                            $room->playback = ['index' => 0, 'startsAt' => (int) (microtime(true) * 1000) + 1500];
                        }
                    }
                } elseif ($action === 'timeout') {
                    $this->engine->revealRound($this->engine->timeout($state));
                } else {
                    abort(404);
                }
                $game->update(['state' => $state->jsonSerialize(), 'status' => $state->phase->value, 'version' => $game->version + 1]);
            }
            $room->version++;
            $room->save();

            return [$room, $index];
        }, 3);
        $this->notify($room);
        if ($action === 'leave') {
            $request->session()->forget('room_tokens.'.$room->id);

            return response()->json()->setData(null);
        }

        return $this->reply($room, $index);
    }

    /** Lobby seats are released; during a match only the host may leave and hosting passes on. */
    private function leave(Room $room, int $index): void
    {
        if ($room->status === 'lobby') {
            $members = $room->members;
            unset($members[$index]);
            $room->members = array_values($members);
            if (! $room->members) {
                $room->status = 'closed';
            } elseif ($index < $room->host) {
                $room->host--;
            } elseif ($index === $room->host) {
                $room->host = $index % count($room->members);
                $room->host_editing_at = null;
            }

            return;
        }
        if ($index !== $room->host && $room->status === 'playing') {
            throw new DomainException('Your seat stays reserved during a match. You can return using this browser.');
        }
        $members = $room->members;
        $members[$index]['left'] = true;
        $room->members = $members;
        $seats = $room->activeSeats();
        if (! $seats) {
            $room->status = 'closed';

            return;
        }
        if ($index === $room->host) {
            $room->host = collect($seats)->first(fn ($seat) => $seat > $index) ?? $seats[0];
        }
        if ($room->status === 'playing') {
            $game = Game::whereKey($room->game_uuid)->lockForUpdate()->firstOrFail();
            $state = GameState::fromArray($game->state);
            $this->pickForDepartedSeat($room, $state);
            $game->update(['state' => $state->jsonSerialize(), 'status' => $state->phase->value, 'version' => $game->version + 1]);
        }
    }

    /** Back to the lobby with the same settings; departed seats are released so new friends can join. */
    private function rematch(Room $room): void
    {
        if ($room->status !== 'finished') {
            throw new DomainException('Finish the draft first.');
        }
        if (($room->playback['startsAt'] ?? 0) + 15000 > (int) (microtime(true) * 1000)) {
            throw new DomainException('Wait for this match to finish.');
        }
        $seats = $room->activeSeats();
        $room->host = array_search($room->host, $seats, true);
        $room->members = array_map(fn ($seat) => [...$room->members[$seat], 'ready' => false], $seats);
        $room->status = 'lobby';
        $room->game_uuid = null;
        $room->playback = null;
        $room->host_editing_at = null;
    }

    /** A departed seat never blocks the draft: its pick is made at random and revealed at once. */
    private function pickForDepartedSeat(Room $room, GameState $state): void
    {
        if ($state->phase === GamePhase::Picking && ($room->members[$state->startingPlayerIndex]['left'] ?? false)) {
            $this->engine->revealRound($this->engine->autoPick($state));
        }
    }

    public function broadcastAuth(Request $request)
    {
        $data = $request->validate(['channel_name' => 'required|string', 'socket_id' => ['required', 'regex:/^\d+\.\d+$/']]);
        abort_unless(preg_match('/^private-room\.([a-f0-9-]{36})$/', $data['channel_name'], $matches), 403);
        $this->access->index($request, Room::findOrFail($matches[1]));

        return response(Broadcast::connection('reverb')->getPusher()->authorizeChannel($data['channel_name'], $data['socket_id']))->header('Content-Type', 'application/json');
    }
}
