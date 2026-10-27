<?php

namespace App\Game;

use App\Models\Game;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;

final class GameStore
{
    /** Compare-and-swap guards stale tabs and duplicate requests, including SQLite. */
    public function mutate(string $uuid, int $expectedVersion, Closure $action): Game
    {
        return DB::transaction(function () use ($uuid, $expectedVersion, $action) {
            $game = Game::whereKey($uuid)->lockForUpdate()->firstOrFail();
            if ($game->version !== $expectedVersion) {
                throw new DomainException(__('The game has changed. Refreshing the current round.'));
            }
            $state = $action(GameState::fromArray($game->state));
            $changed = Game::whereKey($uuid)->where('version', $expectedVersion)->update([
                'state' => json_encode($state, JSON_THROW_ON_ERROR), 'status' => $state->phase->value,
                'version' => $expectedVersion + 1, 'updated_at' => now(),
            ]);
            if ($changed !== 1) {
                throw new DomainException(__('Another action already updated this game.'));
            }

            return $game->refresh();
        }, 3);
    }
}
