<?php

namespace App\Rooms;

use App\Events\RoomChanged;
use App\Models\Room;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Seat, lobby and host bookkeeping shared by every room-based game (Draft Duel and Carta). */
class RoomLobby
{
    public function __construct(private RoomAccess $access) {}

    private function member(string $name, string $token): array
    {
        return ['name' => $name, 'ready' => false, 'token_hash' => hash('sha256', $token)];
    }

    /** Creates a room with the caller as host (seat 0) and remembers their seat token in the session. */
    public function open(Request $request, string $type, string $name, array $settings): Room
    {
        $token = bin2hex(random_bytes(32));
        do {
            $code = strtoupper(Str::random(8));
        } while (Room::where('code', $code)->exists());
        $room = Room::create(['id' => (string) Str::uuid(), 'code' => $code, 'game_type' => $type, 'settings' => $settings,
            'members' => [$this->member($name, $token)], 'expires_at' => now()->addDay()]);
        $request->session()->put('room_tokens.'.$room->id, $token);

        return $room->refresh();
    }

    /** Joins by code, or returns the caller's existing seat when this browser already has one. */
    public function join(Request $request, string $type): array
    {
        $request->merge(['code' => is_string($request->input('code')) ? strtoupper(trim($request->input('code'))) : $request->input('code'), 'name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['code' => 'required|string|size:8|alpha_num:ascii', 'name' => 'required|string|min:2|max:20']);
        $token = bin2hex(random_bytes(32));
        [$room, $index, $existing] = DB::transaction(function () use ($request, $data, $token, $type) {
            $room = Room::where('code', $data['code'])->where('game_type', $type)->lockForUpdate()->firstOrFail();
            abort_if($room->isClosed(), 410, __('This room is closed.'));
            if ($request->session()->has('room_tokens.'.$room->id)) {
                return [$room, $this->access->index($request, $room), true];
            }
            if ($room->status !== 'lobby' || count($room->members) >= $room->settings['capacity']) {
                throw new DomainException(__('This room has started or is full.'));
            }
            foreach ($room->members as $member) {
                if (mb_strtolower($member['name']) === mb_strtolower($data['name'])) {
                    throw new DomainException(__('That name is already taken in this room.'));
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

        return [$room, $index];
    }

    public function setReady(Room $room, int $index, bool $ready): void
    {
        if ($room->status !== 'lobby') {
            throw new DomainException(__('The game has already started.'));
        }
        $members = $room->members;
        $members[$index]['ready'] = $ready;
        $room->members = $members;
    }

    /**
     * Lobby seats are released outright. After kickoff the seat stays in place (game indices never shift),
     * is marked as left, and hosting passes on. Returns true when the seat was kept for a running game.
     */
    public function leave(Room $room, int $index): bool
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

            return false;
        }
        $members = $room->members;
        $members[$index]['left'] = true;
        $room->members = $members;
        $seats = $room->activeSeats();
        if (! $seats) {
            $room->status = 'closed';

            return false;
        }
        if ($index === $room->host) {
            $room->host = collect($seats)->first(fn ($seat) => $seat > $index) ?? $seats[0];
        }

        return $room->status === 'playing';
    }

    /** Back to the lobby with the same settings; departed seats are released so new friends can join. */
    public function rematch(Room $room): void
    {
        if ($room->status !== 'finished') {
            throw new DomainException(__('Finish the game first.'));
        }
        $seats = $room->activeSeats();
        $room->host = array_search($room->host, $seats, true);
        $room->members = array_map(fn ($seat) => [...$room->members[$seat], 'ready' => false], $seats);
        $room->status = 'lobby';
        $room->game_uuid = null;
        $room->playback = null;
        $room->host_editing_at = null;
    }

    public function notify(Room $room): void
    {
        try {
            event(new RoomChanged($room->id, $room->version));
        } catch (\Throwable $error) {
            report($error);
        } // HTTP resync remains available during a socket outage.
    }
}
