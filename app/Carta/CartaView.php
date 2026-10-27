<?php

namespace App\Carta;

use App\Models\CartaGame;
use App\Models\Room;

/**
 * Allowlist serializer for one viewer. Opponents' hands, the draw pile order and drawn cards
 * never appear; only counts and publicly played cards do.
 */
final class CartaView
{
    public function __construct(private CartaEngine $engine, private CartaStore $store) {}

    public function forViewer(Room $room, int $viewer): array
    {
        $game = $room->game_uuid ? CartaGame::find($room->game_uuid) : null;

        return [
            'id' => $room->id, 'code' => $room->code, 'status' => $room->status, 'version' => $room->version,
            'settings' => ['capacity' => $room->settings['capacity']], 'viewerIndex' => $viewer, 'host' => $room->host,
            'isHost' => $viewer === $room->host,
            'members' => array_map(fn ($member) => ['name' => $member['name'], 'ready' => $member['ready'], 'left' => $member['left'] ?? false], $room->members),
            'game' => $game ? $this->game($room, $game, $viewer) : null,
            'serverTimeMs' => (int) round(microtime(true) * 1000),
        ];
    }

    private function game(Room $room, CartaGame $game, int $viewer): array
    {
        $state = $this->store->load($game);
        $legal = $this->engine->legalCards($state, $viewer);
        $me = $state->players[$viewer] ?? null;
        $isTurn = $state->status === CartaState::PLAYING && $state->currentSeat === $viewer;
        $names = array_map(fn ($player) => $player['name'], $state->players);

        return [
            'id' => $game->id, 'status' => $state->status, 'version' => $game->version, 'turnNumber' => $state->turnNumber,
            'currentSeat' => $state->currentSeat, 'activeSuit' => $state->activeSuit,
            'pending' => ['effect' => $state->pendingEffect, 'draw' => $state->pendingDraw],
            'top' => $state->topCard()->toArray(), 'back' => Card::backImage(),
            'drawCount' => count($state->drawPile), 'discardCount' => count($state->discardPile),
            'players' => array_map(fn ($seat) => ['seat' => $seat, 'name' => $state->players[$seat]['name'],
                'cards' => count($state->players[$seat]['hand']), 'status' => $state->players[$seat]['status'],
                'rank' => $state->players[$seat]['rank'], 'isTurn' => $state->currentSeat === $seat,
                'left' => $room->members[$seat]['left'] ?? false], array_keys($state->players)),
            'hand' => $me ? array_map(fn ($id) => [...Card::fromId($id)->toArray(), 'playable' => in_array($id, $legal, true)], $me['hand']) : [],
            'you' => $me ? ['seat' => $viewer, 'status' => $me['status'], 'rank' => $me['rank'], 'isTurn' => $isTurn,
                'canDraw' => $isTurn && $state->pendingEffect === CartaState::NONE,
                'canAccept' => $isTurn && $state->pendingEffect !== CartaState::NONE] : null,
            'rankings' => $this->rankings($state),
            'recent' => $this->recent($game, $names),
        ];
    }

    private function rankings(CartaState $state): array
    {
        $ranked = array_filter($state->players, fn ($player) => $player['rank'] !== null);
        uasort($ranked, fn ($a, $b) => $a['rank'] <=> $b['rank']);

        return array_values(array_map(fn ($seat) => ['seat' => $seat, 'name' => $state->players[$seat]['name'],
            'rank' => $state->players[$seat]['rank'], 'status' => $state->players[$seat]['status']], array_keys($ranked)));
    }

    /** Public move log: played cards are face-up; drawn cards are reported as counts only. */
    private function recent(CartaGame $game, array $names): array
    {
        return $game->moves()->reorder('id', 'desc')->limit(12)->get()->reverse()->values()->map(fn ($move) => [
            'id' => $move->id, 'type' => $move->type, 'seat' => $move->seat, 'name' => $move->seat === null ? null : ($names[$move->seat] ?? null),
            'card' => in_array($move->type, ['play', 'deal'], true) && $move->card ? Card::fromId($move->card)->toArray() : null,
            'suit' => $move->suit, 'amount' => $move->amount,
        ])->all();
    }
}
