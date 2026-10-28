<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\UnoGame;
use App\Rooms\RoomAccess;
use App\Rooms\RoomLobby;
use App\Uno\UnoCard;
use App\Uno\UnoRules;
use App\Uno\UnoState;
use App\Uno\UnoStore;
use App\Uno\UnoVariants;
use App\Uno\UnoView;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** UNO rooms for every variant. The browser only sends intents; the variant's rules engine applies every rule here. */
class UnoController extends Controller
{
    private const GAME_ACTIONS = ['play', 'draw', 'pass', 'accept', 'uno', 'catch'];

    public function __construct(private UnoStore $store, private UnoView $view, private RoomAccess $access, private RoomLobby $lobby) {}

    public function variants()
    {
        return view('uno.variants', ['variants' => UnoVariants::CATALOG]);
    }

    public function page(string $variant)
    {
        abort_unless(UnoVariants::isAvailable($variant), 404);

        return view('uno.table', ['variant' => $variant]);
    }

    private function unoRoom(Room $room): Room
    {
        abort_unless($room->game_type === 'uno', 404);

        return $room;
    }

    private function reply(Room $room, int $index)
    {
        return response()->json($this->view->forViewer($room, $index))->header('Cache-Control', 'no-store, private');
    }

    public function create(Request $request)
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['name' => 'required|string|min:2|max:20', 'capacity' => 'required|integer|between:2,6',
            'variant' => ['required', 'in:'.implode(',', UnoVariants::available())], 'stacking' => ['required', 'in:'.implode(',', UnoState::STACKING)]]);
        $settings = ['capacity' => (int) $data['capacity'], 'variant' => $data['variant'], 'stacking' => $data['stacking']];

        return $this->reply($this->lobby->open($request, 'uno', $data['name'], $settings), 0);
    }

    public function join(Request $request)
    {
        [$room, $index] = $this->lobby->join($request, 'uno');

        return $this->reply($room, $index);
    }

    public function state(Request $request, Room $room)
    {
        $this->unoRoom($room);

        return $this->reply($room, $this->access->index($request, $room));
    }

    public function action(Request $request, Room $room, string $action)
    {
        $this->unoRoom($room);
        $request->validate(['version' => 'required|integer|min:0', 'ready' => 'sometimes|boolean',
            'stacking' => [$action === 'settings' ? 'sometimes' : 'prohibited', 'in:'.implode(',', UnoState::STACKING)],
            'capacity' => [$action === 'settings' ? 'sometimes' : 'prohibited', 'integer', 'between:2,6'],
            'actionId' => [in_array($action, self::GAME_ACTIONS, true) ? 'required' : 'sometimes', 'uuid'],
            'card' => [$action === 'play' ? 'required' : 'prohibited', 'string', 'max:20'],
            'color' => ['sometimes', 'nullable', 'in:'.implode(',', UnoCard::COLORS)],
            'target' => [in_array($action, ['catch', 'kick'], true) ? 'required' : 'prohibited', 'integer', 'between:0,5']]);

        [$room, $index] = DB::transaction(function () use ($request, $room, $action) {
            $room = Room::whereKey($room->id)->lockForUpdate()->firstOrFail();
            $index = $this->access->index($request, $room);
            $game = $room->game_uuid ? $this->store->lock($room->game_uuid) : null;
            // A retried request (double click, flaky network) is answered with the current state, never applied twice.
            if ($game && $this->store->alreadyApplied($game, $request->input('actionId'))) {
                return [$room, $index];
            }
            if ($room->version !== $request->integer('version')) {
                throw new DomainException(__('The table changed. Please try again.'));
            }
            match ($action) {
                'ready' => $this->lobby->setReady($room, $index, $request->boolean('ready')),
                'settings' => $this->settings($room, $index, $request->input('stacking'), $request->input('capacity')),
                'kick' => $this->kick($room, $index, $request->integer('target'), $game),
                'start' => $this->start($room, $index),
                'rematch' => $this->lobby->rematch($room),
                'leave' => $this->leave($room, $index, $game),
                default => $this->move($room, $game, $index, $action, $request),
            };
            $room->version++;
            $room->save();

            return [$room, $index];
        }, 3);
        $this->lobby->notify($room);
        if ($action === 'leave') {
            $request->session()->forget('room_tokens.'.$room->id);

            return response()->json()->setData(null);
        }

        return $this->reply($room, $index);
    }

    private function rules(Room $room): UnoRules
    {
        return UnoVariants::get($room->settings['variant'])->rules();
    }

    /**
     * The host's room settings: seats and the stacking rule. Both are lobby-only; the rule is copied onto the match
     * at the deal and locked from then on. Seats can never drop below the players already sitting.
     */
    private function settings(Room $room, int $index, ?string $stacking, mixed $capacity): void
    {
        abort_unless($index === $room->host, 403);
        if ($room->status !== 'lobby') {
            throw new DomainException(__('Rules are locked once the cards are dealt.'));
        }
        if ($capacity !== null) {
            if ((int) $capacity < count($room->members)) {
                throw new DomainException(__('Remove a player before taking their seat away.'));
            }
            $room->settings = [...$room->settings, 'capacity' => (int) $capacity];
        }
        if ($stacking !== null && $stacking !== UnoState::stackingOf($room->settings['stacking'])) {
            $room->settings = [...$room->settings, 'stacking' => $stacking];
            // Everyone confirms again under the new rule.
            $room->members = array_map(fn ($member) => [...$member, 'ready' => false], $room->members);
        }
    }

    /** The host removes a player: in the lobby the seat frees up; mid-match it counts as leaving (a forfeit). */
    private function kick(Room $room, int $index, int $target, ?UnoGame $game): void
    {
        abort_unless($index === $room->host, 403);
        if ($target === $index || ! isset($room->members[$target]) || ($room->members[$target]['left'] ?? false)) {
            throw new DomainException(__('That player is not at the table.'));
        }
        $room->settings = [...$room->settings, 'kicked' => [...($room->settings['kicked'] ?? []), $room->members[$target]['token_hash']]];
        $this->leave($room, $target, $game);
    }

    private function start(Room $room, int $index): void
    {
        abort_unless($index === $room->host, 403);
        if ($room->status !== 'lobby') {
            throw new DomainException(__('This match has already started.'));
        }
        if (count($room->members) < 2 || in_array(false, array_column($room->members, 'ready'), true)) {
            throw new DomainException(__('Wait for at least two players, all of them ready.'));
        }
        $state = $this->rules($room)->start(array_column($room->members, 'name'), UnoState::stackingOf($room->settings['stacking']));
        $game = $this->store->create($room, $room->settings['variant'], $state);
        $room->game_uuid = $game->id;
        $room->status = 'playing';
    }

    private function move(Room $room, ?UnoGame $game, int $seat, string $action, Request $request): void
    {
        if ($room->status !== 'playing' || ! $game) {
            throw new DomainException(__('This match is not running.'));
        }
        $rules = $this->rules($room);
        $state = $this->store->load($game);
        match ($action) {
            'play' => $rules->play($state, $seat, $this->cardId($request->input('card')), $request->input('color')),
            'draw' => $rules->draw($state, $seat),
            'pass' => $rules->pass($state, $seat),
            'accept' => $rules->accept($state, $seat),
            'uno' => $rules->callUno($state, $seat),
            'catch' => $rules->catchUno($state, $seat, $request->integer('target')),
        };

        $this->store->save($game, $state, $request->input('actionId'));
        if ($state->status === UnoState::FINISHED) {
            $room->status = 'finished';
        }
    }

    private function cardId(mixed $card): string
    {
        try {
            return UnoCard::fromId((string) $card)->id();
        } catch (\InvalidArgumentException) {
            throw new DomainException(__('Unknown card.'));
        }
    }

    /** Leaving mid-match forfeits the seat; if only one player is left, they win. */
    private function leave(Room $room, int $index, ?UnoGame $game): void
    {
        $seatKept = $this->lobby->leave($room, $index);
        if (! $game || $game->status !== UnoState::PLAYING) {
            return;
        }
        if ($room->status === 'closed') {
            $game->update(['status' => UnoState::CANCELLED, 'version' => $game->version + 1]);

            return;
        }
        if ($seatKept) {
            $state = $this->rules($room)->forfeit($this->store->load($game), $index);
            $this->store->save($game, $state);
            if ($state->status === UnoState::FINISHED) {
                $room->status = 'finished';
            }
        }
    }
}
