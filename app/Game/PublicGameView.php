<?php

namespace App\Game;

use App\Enums\GamePhase;

/** Allowlist serializer: private DTOs must never be returned directly. */
final class PublicGameView
{
    public function transform(GameState $state, int $version): array
    {
        $round = $state->rounds[$state->currentRound] ?? null;
        $visible = in_array($state->phase, [GamePhase::Revealing, GamePhase::Finished], true);
        $cards = [];
        if ($round && $state->phase !== GamePhase::PassDevice) {
            $cards = [array_merge($round['cards'][0], ['selectionId' => $round['tokens'][0], 'hidden' => false]),
                $visible ? array_merge($round['cards'][1], ['selectionId' => $round['tokens'][1], 'hidden' => false])
                    : ['selectionId' => $round['tokens'][1], 'hidden' => true]];
        }

        return [
            'phase' => $state->phase->value, 'domainKey' => $state->domainKey, 'catalog' => $state->catalog, 'version' => $version,
            'currentRound' => $state->currentRound, 'roundLimit' => $state->roundLimit,
            'cardsPerPlayer' => $state->cardsPerPlayer ?? $state->roundLimit,
            'formation' => $state->formation, 'activeSlot' => $state->formation[$state->schedule[$state->currentRound]['slotIndex'] ?? $state->currentRound] ?? null,
            'recipientPlayer' => $state->schedule[$state->currentRound]['recipientPlayer'] ?? ($state->startingPlayerIndex + 1) % count($state->players),
            'extraPlayer' => $state->schedule[$state->currentRound]['extraPlayer'] ?? null,
            'extraCard' => $visible ? ($round['cards'][2] ?? null) : null,
            'powerMax' => $state->domainKey === 'football' ? 100 : 1000,
            'activePlayer' => $state->startingPlayerIndex, 'timerEnabled' => $state->timerEnabled,
            'pickDeadline' => $state->pickDeadline, 'serverTime' => time(), 'cards' => $cards,
            'pickedSlot' => $round['pickedSlot'] ?? null,
            'players' => array_map(fn ($player) => ['name' => $player['name'], 'cards' => $player['cards'],
                'total' => array_sum(array_column($player['cards'], 'power'))], $state->players),
            'results' => $state->results,
            'recap' => array_values(array_map(fn ($round) => ['cards' => $round['cards'], 'activePlayer' => $round['activePlayer'],
                'recipientPlayer' => $round['recipientPlayer'], 'extraPlayer' => $round['extraPlayer'] ?? null, 'pickedSlot' => $round['pickedSlot']],
                array_filter($state->rounds, fn ($round) => $round['revealed']))),
        ];
    }
}
