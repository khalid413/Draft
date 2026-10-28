<?php

namespace App\Uno;

use App\Models\Room;
use App\Models\UnoGame;

/**
 * Allowlist serializer for one viewer. Opponents' hands, the draw pile order, drawn cards and
 * whether a +4 was a bluff never appear; only counts and publicly played cards do.
 */
final class UnoView
{
    public function __construct(private UnoStore $store) {}

    public function forViewer(Room $room, int $viewer): array
    {
        $game = $room->game_uuid ? UnoGame::find($room->game_uuid) : null;

        return [
            'id' => $room->id, 'code' => $room->code, 'status' => $room->status, 'version' => $room->version,
            'settings' => ['capacity' => $room->settings['capacity'], 'variant' => $room->settings['variant'], 'stacking' => UnoState::stackingOf($room->settings['stacking'])],
            'viewerIndex' => $viewer, 'host' => $room->host, 'isHost' => $viewer === $room->host,
            'members' => array_map(fn ($member) => ['name' => $member['name'], 'ready' => $member['ready'], 'left' => $member['left'] ?? false], $room->members),
            'game' => $game ? $this->game($room, $game, $viewer) : null,
            'serverTimeMs' => (int) round(microtime(true) * 1000),
        ];
    }

    private function game(Room $room, UnoGame $game, int $viewer): array
    {
        $variant = UnoVariants::get($game->variant);
        $rules = $variant->rules();
        $state = $this->store->load($game);
        $legal = $rules->legalCards($state, $viewer);
        $me = $state->players[$viewer] ?? null;
        $isTurn = $state->status === UnoState::PLAYING && $state->currentSeat === $viewer;
        $names = array_map(fn ($player) => $player['name'], $state->players);
        $card = fn (string $id) => $this->card($variant, $id);

        return [
            'id' => $game->id, 'variant' => $game->variant, 'stacking' => $state->stacking, 'status' => $state->status, 'version' => $game->version,
            'turnNumber' => $state->turnNumber, 'currentSeat' => $state->currentSeat, 'direction' => $state->direction, 'activeColor' => $state->activeColor,
            'pending' => ['effect' => $state->pendingEffect, 'draw' => $state->pendingDraw],
            'top' => $card($state->discardPile[array_key_last($state->discardPile)]), 'back' => $variant->backImage(),
            'drawCount' => count($state->drawPile), 'discardCount' => count($state->discardPile),
            'players' => array_map(fn ($seat) => ['seat' => $seat, 'name' => $state->players[$seat]['name'],
                'cards' => count($state->players[$seat]['hand']), 'status' => $state->players[$seat]['status'],
                'rank' => $state->players[$seat]['rank'], 'isTurn' => $state->currentSeat === $seat,
                'uno' => $state->players[$seat]['uno'] && count($state->players[$seat]['hand']) === 1,
                'catchable' => $state->unoExposed === $seat && $seat !== $viewer && $state->isActive($viewer),
                'left' => $room->members[$seat]['left'] ?? false], array_keys($state->players)),
            'hand' => $me ? array_map(fn ($id) => [...$card($id), 'playable' => in_array($id, $legal, true), 'drawn' => $id === $state->drawnCard], $me['hand']) : [],
            'you' => $me ? ['seat' => $viewer, 'status' => $me['status'], 'rank' => $me['rank'], 'isTurn' => $isTurn,
                'canDraw' => $isTurn && $state->pendingEffect === UnoState::NONE && $state->drawnCard === null,
                'canPass' => $isTurn && $state->drawnCard !== null,
                'canAccept' => $isTurn && $state->pendingEffect !== UnoState::NONE,
                'canCallUno' => $rules->canCallUno($state, $viewer), 'unoCalled' => $me['uno'], 'exposed' => $state->unoExposed === $viewer] : null,
            'rankings' => $this->rankings($state),
            'recent' => $this->recent($variant, $game, $names),
        ];
    }

    private function card(UnoVariant $variant, string $id): array
    {
        $card = UnoCard::fromId($id);

        return ['id' => $id, 'color' => $card->color, 'value' => $card->value, 'image' => $variant->image($card)];
    }

    private function rankings(UnoState $state): array
    {
        $ranked = array_filter($state->players, fn ($player) => $player['rank'] !== null);
        uasort($ranked, fn ($a, $b) => $a['rank'] <=> $b['rank']);

        return array_values(array_map(fn ($seat) => ['seat' => $seat, 'name' => $state->players[$seat]['name'],
            'rank' => $state->players[$seat]['rank'], 'status' => $state->players[$seat]['status']], array_keys($ranked)));
    }

    /** Public move log: played cards are face-up; drawn cards are reported as counts only. */
    private function recent(UnoVariant $variant, UnoGame $game, array $names): array
    {
        return $game->moves()->reorder('id', 'desc')->limit(12)->get()->reverse()->values()->map(fn ($move) => [
            'id' => $move->id, 'type' => $move->type, 'seat' => $move->seat, 'name' => $move->seat === null ? null : ($names[$move->seat] ?? null),
            'target' => $move->target, 'targetName' => $move->target === null ? null : ($names[$move->target] ?? null),
            'card' => in_array($move->type, ['play', 'deal'], true) && $move->card ? $this->card($variant, $move->card) : null,
            'color' => $move->color, 'amount' => $move->amount,
        ])->all();
    }
}
