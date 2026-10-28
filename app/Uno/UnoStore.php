<?php

namespace App\Uno;

use App\Models\Room;
use App\Models\UnoGame;
use App\Models\UnoMove;
use App\Models\UnoPlayer;

/** Maps UnoState to its tables: the game row, one row per seat (hand, status, rank, UNO call) and the move history. */
final class UnoStore
{
    /** Seconds each player gets per turn. */
    public const TURN_SECONDS = 10;

    /** Extra time before the clock counts, covering the card animations that hand the turn over. */
    private const HANDOVER_MS = 1500;

    public function create(Room $room, string $variant, UnoState $state): UnoGame
    {
        $game = UnoGame::create(['room_id' => $room->id, 'variant' => $variant, 'stacking' => $state->stacking, 'status' => $state->status,
            'draw_pile' => [], 'discard_pile' => [], 'started_at' => now()]);
        foreach ($state->players as $seat => $player) {
            UnoPlayer::create(['uno_game_id' => $game->id, 'seat' => $seat, 'name' => $player['name'], 'hand' => $player['hand'], 'status' => $player['status']]);
        }
        $this->save($game, $state);

        return $game->refresh();
    }

    /** Call inside a transaction; the row lock serializes concurrent actions on the same match. */
    public function lock(string $gameId): UnoGame
    {
        return UnoGame::whereKey($gameId)->lockForUpdate()->firstOrFail();
    }

    public function load(UnoGame $game): UnoState
    {
        $players = [];
        foreach ($game->players()->get() as $row) {
            $players[$row->seat] = ['name' => $row->name, 'hand' => $row->hand, 'status' => $row->status, 'rank' => $row->finish_rank, 'uno' => $row->uno_called];
        }

        return new UnoState(status: $game->status, players: $players, drawPile: $game->draw_pile, discardPile: $game->discard_pile,
            currentSeat: $game->current_seat, direction: $game->direction, activeColor: $game->active_color,
            pendingEffect: $game->pending_effect, pendingDraw: $game->pending_draw, drawnCard: $game->drawn_card,
            unoExposed: $game->uno_exposed, stacking: $game->stacking, turnNumber: $game->turn_number);
    }

    /** Writes the state, bumps the version and appends the action's moves; the first move carries the client's action id. */
    public function save(UnoGame $game, UnoState $state, ?string $actionId = null): void
    {
        // A new turn starts a new clock; actions inside the same turn (draw, UNO, a catch) leave it running.
        $newTurn = $game->turn_ends_at === null || $state->turnNumber !== $game->turn_number;
        $endsAt = $state->status !== UnoState::PLAYING ? null
            : ($newTurn ? (int) floor(microtime(true) * 1000) + self::TURN_SECONDS * 1000 + self::HANDOVER_MS : $game->turn_ends_at);
        $game->update(['turn_ends_at' => $endsAt, 'status' => $state->status, 'turn_number' => $state->turnNumber, 'current_seat' => $state->currentSeat,
            'direction' => $state->direction, 'active_color' => $state->activeColor, 'pending_effect' => $state->pendingEffect,
            'pending_draw' => $state->pendingDraw, 'drawn_card' => $state->drawnCard,
            'uno_exposed' => $state->unoExposed, 'draw_pile' => $state->drawPile, 'discard_pile' => $state->discardPile,
            'version' => $game->version + 1, 'finished_at' => $state->status === UnoState::FINISHED ? ($game->finished_at ?? now()) : null]);
        $rows = $game->players()->get()->keyBy('seat');
        foreach ($state->players as $seat => $player) {
            $row = $rows[$seat];
            $finishing = $row->finish_rank === null && $player['rank'] !== null;
            $row->update(['hand' => $player['hand'], 'status' => $player['status'], 'finish_rank' => $player['rank'], 'uno_called' => $player['uno'],
                'finished_at' => $finishing ? now() : $row->finished_at]);
        }
        foreach ($state->events as $i => $event) {
            UnoMove::create(['uno_game_id' => $game->id, 'action_id' => $i === 0 ? $actionId : null, 'turn_number' => $state->turnNumber,
                'seat' => $event['seat'] ?? null, 'type' => $event['type'], 'card' => $event['card'] ?? null,
                'color' => $event['color'] ?? null, 'target' => $event['target'] ?? null, 'amount' => $event['amount'] ?? null]);
        }
        $state->events = [];
    }

    /** True once the current turn's clock has run out. */
    public function turnExpired(UnoGame $game): bool
    {
        return $game->turn_ends_at !== null && microtime(true) * 1000 >= $game->turn_ends_at;
    }

    public function alreadyApplied(UnoGame $game, ?string $actionId): bool
    {
        return $actionId !== null && UnoMove::where('uno_game_id', $game->id)->where('action_id', $actionId)->exists();
    }
}
