<?php

namespace App\Carta;

use App\Models\CartaGame;
use App\Models\CartaMove;
use App\Models\CartaPlayer;
use App\Models\Room;

/** Maps CartaState to its tables: the game row, one row per seat (hand, status, rank) and the move history. */
final class CartaStore
{
    public function create(Room $room, CartaState $state): CartaGame
    {
        $game = CartaGame::create(['room_id' => $room->id, 'status' => $state->status, 'draw_pile' => [], 'discard_pile' => [], 'started_at' => now()]);
        foreach ($state->players as $seat => $player) {
            CartaPlayer::create(['carta_game_id' => $game->id, 'seat' => $seat, 'name' => $player['name'], 'hand' => $player['hand'], 'status' => $player['status']]);
        }
        $this->save($game, $state);

        return $game->refresh();
    }

    /** Call inside a transaction; the row lock serializes concurrent actions on the same match. */
    public function lock(string $gameId): CartaGame
    {
        return CartaGame::whereKey($gameId)->lockForUpdate()->firstOrFail();
    }

    public function load(CartaGame $game): CartaState
    {
        $players = [];
        foreach ($game->players()->get() as $row) {
            $players[$row->seat] = ['name' => $row->name, 'hand' => $row->hand, 'status' => $row->status, 'rank' => $row->finish_rank];
        }

        return new CartaState(status: $game->status, players: $players, drawPile: $game->draw_pile, discardPile: $game->discard_pile,
            currentSeat: $game->current_seat, activeSuit: $game->active_suit, pendingEffect: $game->pending_effect,
            pendingDraw: $game->pending_draw, drawnCard: $game->drawn_card, turnNumber: $game->turn_number);
    }

    /** Writes the state, bumps the version and appends the action's moves; the first move carries the client's action id. */
    public function save(CartaGame $game, CartaState $state, ?string $actionId = null): void
    {
        $game->update(['status' => $state->status, 'turn_number' => $state->turnNumber, 'current_seat' => $state->currentSeat,
            'active_suit' => $state->activeSuit, 'pending_effect' => $state->pendingEffect, 'pending_draw' => $state->pendingDraw,
            'drawn_card' => $state->drawnCard, 'draw_pile' => $state->drawPile, 'discard_pile' => $state->discardPile,
            'version' => $game->version + 1, 'finished_at' => $state->status === CartaState::FINISHED ? ($game->finished_at ?? now()) : null]);
        $rows = $game->players()->get()->keyBy('seat');
        foreach ($state->players as $seat => $player) {
            $row = $rows[$seat];
            $finishing = $row->finish_rank === null && $player['rank'] !== null;
            $row->update(['hand' => $player['hand'], 'status' => $player['status'], 'finish_rank' => $player['rank'],
                'finished_at' => $finishing ? now() : $row->finished_at]);
        }
        foreach ($state->events as $i => $event) {
            CartaMove::create(['carta_game_id' => $game->id, 'action_id' => $i === 0 ? $actionId : null, 'turn_number' => $state->turnNumber,
                'seat' => $event['seat'] ?? null, 'type' => $event['type'], 'card' => $event['card'] ?? null,
                'suit' => $event['suit'] ?? null, 'amount' => $event['amount'] ?? null]);
        }
        $state->events = [];
    }

    public function alreadyApplied(CartaGame $game, ?string $actionId): bool
    {
        return $actionId !== null && CartaMove::where('carta_game_id', $game->id)->where('action_id', $actionId)->exists();
    }
}
