<?php

namespace App\Http\Controllers;

use App\Carta\Card;
use App\Carta\CartaEngine;
use App\Carta\CartaState;
use App\Carta\CartaStore;
use App\Carta\CartaView;
use App\Models\CartaGame;
use App\Models\Room;
use App\Rooms\RoomAccess;
use App\Rooms\RoomLobby;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Moroccan Carta rooms. The browser only sends intents; every rule is applied by CartaEngine here. */
class CartaController extends Controller
{
    private const GAME_ACTIONS = ['play', 'draw', 'accept'];

    public function __construct(private CartaEngine $engine, private CartaStore $store, private CartaView $view,
        private RoomAccess $access, private RoomLobby $lobby) {}

    public function page()
    {
        return view('carta');
    }

    private function cartaRoom(Room $room): Room
    {
        abort_unless($room->game_type === 'carta', 404);

        return $room;
    }

    private function reply(Room $room, int $index)
    {
        return response()->json($this->view->forViewer($room, $index))->header('Cache-Control', 'no-store, private');
    }

    public function create(Request $request)
    {
        $request->merge(['name' => is_string($request->input('name')) ? trim($request->input('name')) : $request->input('name')]);
        $data = $request->validate(['name' => 'required|string|min:2|max:20', 'capacity' => 'required|integer|between:2,6']);

        return $this->reply($this->lobby->open($request, 'carta', $data['name'], ['capacity' => (int) $data['capacity']]), 0);
    }

    public function join(Request $request)
    {
        [$room, $index] = $this->lobby->join($request, 'carta');

        return $this->reply($room, $index);
    }

    public function state(Request $request, Room $room)
    {
        $this->cartaRoom($room);

        return $this->reply($room, $this->access->index($request, $room));
    }

    public function action(Request $request, Room $room, string $action)
    {
        $this->cartaRoom($room);
        $request->validate(['version' => 'required|integer|min:0', 'ready' => 'sometimes|boolean',
            'actionId' => [in_array($action, self::GAME_ACTIONS, true) ? 'required' : 'sometimes', 'uuid'],
            'card' => [$action === 'play' ? 'required' : 'prohibited', 'string', 'max:12'],
            'suit' => ['sometimes', 'nullable', 'in:'.implode(',', Card::SUITS)]]);

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

    private function start(Room $room, int $index): void
    {
        abort_unless($index === $room->host, 403);
        if ($room->status !== 'lobby') {
            throw new DomainException(__('This match has already started.'));
        }
        if (count($room->members) < 2 || in_array(false, array_column($room->members, 'ready'), true)) {
            throw new DomainException(__('Wait for at least two players, all of them ready.'));
        }
        $game = $this->store->create($room, $this->engine->start(array_column($room->members, 'name')));
        $room->game_uuid = $game->id;
        $room->status = 'playing';
    }

    private function move(Room $room, ?CartaGame $game, int $seat, string $action, Request $request): void
    {
        if ($room->status !== 'playing' || ! $game) {
            throw new DomainException(__('This match is not running.'));
        }
        $state = $this->store->load($game);
        match ($action) {
            'play' => $this->engine->play($state, $seat, $this->cardId($request->input('card')), $request->input('suit')),
            'draw' => $this->engine->draw($state, $seat),
            'accept' => $this->engine->accept($state, $seat),
        };

        $this->store->save($game, $state, $request->input('actionId'));
        if ($state->status === CartaState::FINISHED) {
            $room->status = 'finished';
        }
    }

    private function cardId(mixed $card): string
    {
        try {
            return Card::fromId((string) $card)->id();
        } catch (\InvalidArgumentException) {
            throw new DomainException(__('Unknown card.'));
        }
    }

    /** Leaving mid-match forfeits the seat; if only one player is left, they win. */
    private function leave(Room $room, int $index, ?CartaGame $game): void
    {
        $seatKept = $this->lobby->leave($room, $index);
        if (! $game || $game->status !== CartaState::PLAYING) {
            return;
        }
        if ($room->status === 'closed') {
            $game->update(['status' => CartaState::CANCELLED, 'version' => $game->version + 1]);

            return;
        }
        if ($seatKept) {
            $state = $this->engine->forfeit($this->store->load($game), $index);
            $this->store->save($game, $state);
            if ($state->status === CartaState::FINISHED) {
                $room->status = 'finished';
            }
        }
    }
}
