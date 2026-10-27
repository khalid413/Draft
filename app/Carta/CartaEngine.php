<?php

namespace App\Carta;

use DomainException;
use LogicException;
use Random\Randomizer;

/**
 * Transport-free rules of Moroccan Carta. Every method validates the move, mutates the state,
 * and records public history in $state->events. Nothing here trusts the client.
 */
final class CartaEngine
{
    public const HAND_SIZE = 5;

    private Randomizer $random;

    public function __construct(?Randomizer $random = null)
    {
        $this->random = $random ?? new Randomizer;
    }

    /** Shuffles a fresh deck, deals five cards each and turns up the starting card (its effect is ignored). */
    public function start(array $names, ?int $startingSeat = null): CartaState
    {
        $names = array_values($names);
        if (count($names) < 2 || count($names) > 6) {
            throw new DomainException('Carta needs 2 to 6 players.');
        }
        $pile = $this->random->shuffleArray(array_map(fn (Card $card) => $card->id(), Card::deck()));
        $players = array_map(fn ($name) => ['name' => $name, 'hand' => [], 'status' => CartaState::ACTIVE, 'rank' => null], $names);
        for ($round = 0; $round < self::HAND_SIZE; $round++) {
            foreach (array_keys($players) as $seat) {
                $players[$seat]['hand'][] = array_pop($pile);
            }
        }
        $starter = array_pop($pile);
        $state = new CartaState(status: CartaState::PLAYING, players: $players, drawPile: $pile, discardPile: [$starter],
            currentSeat: $startingSeat ?? $this->random->getInt(0, count($players) - 1), activeSuit: Card::fromId($starter)->suit, turnNumber: 1);
        $state->events[] = ['type' => 'deal', 'seat' => null, 'card' => $starter, 'amount' => self::HAND_SIZE];
        $this->assertConserved($state);

        return $state;
    }

    /** Card ids the seat may legally play right now (empty when it is not their turn). */
    public function legalCards(CartaState $state, int $seat): array
    {
        if ($state->status !== CartaState::PLAYING || $state->currentSeat !== $seat || ! $state->isActive($seat)) {
            return [];
        }
        // After drawing, only the card just drawn may still be played this turn.
        $candidates = $state->drawnCard !== null ? [$state->drawnCard] : $state->players[$seat]['hand'];

        return array_values(array_filter($candidates, fn ($id) => $this->canPlay($state, Card::fromId($id))));
    }

    private function canPlay(CartaState $state, Card $card): bool
    {
        return match ($state->pendingEffect) {
            CartaState::SKIP => $card->rank === 1,
            CartaState::DRAW_TWO => $card->rank === 2,
            default => $card->rank === 7 || $card->suit === $state->activeSuit || $card->rank === $state->topCard()->rank,
        };
    }

    public function play(CartaState $state, int $seat, string $cardId, ?string $suit = null): CartaState
    {
        $this->assertTurn($state, $seat);
        $card = Card::fromId($cardId);
        $hand = $state->players[$seat]['hand'];
        $position = array_search($cardId, $hand, true);
        if ($position === false) {
            throw new DomainException('That card is not in your hand.');
        }
        if ($state->drawnCard !== null && $cardId !== $state->drawnCard) {
            throw new DomainException('After drawing you can only play the card you drew, or pass.');
        }
        if (! $this->canPlay($state, $card)) {
            throw new DomainException(match ($state->pendingEffect) {
                CartaState::SKIP => 'Answer the skip with a 1, or accept it.',
                CartaState::DRAW_TWO => 'Answer with a 2, or draw the penalty cards.',
                default => 'That card does not match the suit or number on the table.',
            });
        }
        $finishing = count($hand) === 1;
        if ($card->rank === 7 && ! $finishing && ! Card::suitIsValid($suit)) {
            throw new DomainException('Choose the next suit for your 7.');
        }

        unset($hand[$position]);
        $state->players[$seat]['hand'] = array_values($hand);
        $state->discardPile[] = $cardId;
        $state->drawnCard = null;
        $state->events[] = ['type' => 'play', 'seat' => $seat, 'card' => $cardId, 'suit' => $card->rank === 7 && ! $finishing ? $suit : null];

        if ($finishing) {
            // The last card ends this player's game: any effect it would trigger, and any chain it answered, is cancelled.
            $state->activeSuit = $card->suit;
            $state->pendingEffect = CartaState::NONE;
            $state->pendingDraw = 0;
            $this->finishPlayer($state, $seat);
            if ($state->status === CartaState::PLAYING) {
                $this->advance($state, $seat);
            }
        } else {
            $state->activeSuit = $card->rank === 7 ? $suit : $card->suit;
            if ($card->rank === 1) {
                $state->pendingEffect = CartaState::SKIP;
            } elseif ($card->rank === 2) {
                $state->pendingEffect = CartaState::DRAW_TWO;
                $state->pendingDraw += 2;
            }
            $this->advance($state, $seat);
        }
        $this->assertConserved($state);

        return $state;
    }

    /** Only allowed with no playable card and no pending effect. A playable drawn card may be played right away. */
    public function draw(CartaState $state, int $seat): CartaState
    {
        $this->assertTurn($state, $seat);
        if ($state->pendingEffect !== CartaState::NONE) {
            throw new DomainException('Answer the special card or accept its penalty first.');
        }
        if ($state->drawnCard !== null) {
            throw new DomainException('You already drew this turn.');
        }
        if ($this->legalCards($state, $seat)) {
            throw new DomainException('You have a playable card, so you cannot draw.');
        }
        $cards = $this->take($state, 1);
        $state->players[$seat]['hand'] = [...$state->players[$seat]['hand'], ...$cards];
        $state->events[] = ['type' => 'draw', 'seat' => $seat, 'card' => $cards[0] ?? null, 'amount' => count($cards)];
        if ($cards && $this->canPlay($state, Card::fromId($cards[0]))) {
            $state->drawnCard = $cards[0];
        } else {
            $this->advance($state, $seat);
        }
        $this->assertConserved($state);

        return $state;
    }

    /** Takes the pending skip (lose the turn) or draw penalty (draw them all, lose the turn). */
    public function accept(CartaState $state, int $seat): CartaState
    {
        $this->assertTurn($state, $seat);
        if ($state->pendingEffect === CartaState::NONE) {
            throw new DomainException('There is no penalty to accept.');
        }
        if ($state->pendingEffect === CartaState::SKIP) {
            $state->events[] = ['type' => 'accept_skip', 'seat' => $seat];
        } else {
            $cards = $this->take($state, $state->pendingDraw);
            $state->players[$seat]['hand'] = [...$state->players[$seat]['hand'], ...$cards];
            $state->events[] = ['type' => 'accept_draw', 'seat' => $seat, 'amount' => count($cards)];
        }
        $state->pendingEffect = CartaState::NONE;
        $state->pendingDraw = 0;
        $this->advance($state, $seat);
        $this->assertConserved($state);

        return $state;
    }

    /** Ends the turn after drawing a card the player chooses not to play. */
    public function pass(CartaState $state, int $seat): CartaState
    {
        $this->assertTurn($state, $seat);
        if ($state->drawnCard === null) {
            throw new DomainException('You can only pass after drawing a playable card.');
        }
        $state->events[] = ['type' => 'pass', 'seat' => $seat];
        $this->advance($state, $seat);

        return $state;
    }

    /** Plays the current turn for a seat whose player left the room, so the match never stalls. */
    public function autoAct(CartaState $state): CartaState
    {
        $seat = $state->currentSeat;
        if ($state->pendingEffect !== CartaState::NONE) {
            return $this->accept($state, $seat);
        }
        $legal = $this->legalCards($state, $seat);
        if (! $legal && $state->drawnCard === null) {
            $this->draw($state, $seat);
            if ($state->currentSeat !== $seat || $state->drawnCard === null) {
                return $state;
            }
            $legal = [$state->drawnCard];
        }
        $card = Card::fromId($legal[0]);

        return $this->play($state, $seat, $legal[0], $card->rank === 7 ? $this->favouriteSuit($state, $seat, $card) : null);
    }

    private function favouriteSuit(CartaState $state, int $seat, Card $playing): string
    {
        $counts = array_count_values(array_map(fn ($id) => Card::fromId($id)->suit,
            array_filter($state->players[$seat]['hand'], fn ($id) => $id !== $playing->id())));
        arsort($counts);

        return array_key_first($counts) ?? $playing->suit;
    }

    private function assertTurn(CartaState $state, int $seat): void
    {
        if ($state->status !== CartaState::PLAYING) {
            throw new DomainException('This match is not running.');
        }
        if (! isset($state->players[$seat])) {
            throw new DomainException('You are not seated at this table.');
        }
        if (! $state->isActive($seat)) {
            throw new DomainException('You have finished this match. Watch the others play it out.');
        }
        if ($state->currentSeat !== $seat) {
            throw new DomainException('It is not your turn.');
        }
    }

    /** Next seat clockwise that still holds cards; finished players are skipped entirely. */
    private function nextActiveSeat(CartaState $state, int $from): int
    {
        $count = count($state->players);
        for ($step = 1; $step <= $count; $step++) {
            $seat = ($from + $step) % $count;
            if ($state->isActive($seat)) {
                return $seat;
            }
        }
        throw new LogicException('No active player left.');
    }

    private function advance(CartaState $state, int $from): void
    {
        $state->currentSeat = $this->nextActiveSeat($state, $from);
        $state->drawnCard = null;
        $state->turnNumber++;
    }

    /** Draws up to $count cards, reshuffling the discard pile (keeping its top card) when the draw pile runs out. */
    private function take(CartaState $state, int $count): array
    {
        $cards = [];
        while (count($cards) < $count) {
            if (! $state->drawPile) {
                $top = array_pop($state->discardPile);
                if (! $state->discardPile) {
                    $state->discardPile = [$top];
                    break; // Every other card is in a hand.
                }
                $state->drawPile = $this->random->shuffleArray($state->discardPile);
                $state->discardPile = [$top];
                $state->events[] = ['type' => 'reshuffle', 'seat' => null, 'amount' => count($state->drawPile)];
            }
            $cards[] = array_pop($state->drawPile);
        }

        return $cards;
    }

    /** Ranks the seat; when a single active player remains they are the loser and the match ends. */
    private function finishPlayer(CartaState $state, int $seat): void
    {
        $rank = count(array_filter($state->players, fn ($player) => $player['rank'] !== null)) + 1;
        $state->players[$seat]['status'] = CartaState::DONE;
        $state->players[$seat]['rank'] = $rank;
        $state->events[] = ['type' => 'finish', 'seat' => $seat, 'amount' => $rank];
        $active = $state->activeSeats();
        if (count($active) === 1) {
            $loser = $active[0];
            $state->players[$loser]['status'] = CartaState::LOSER;
            $state->players[$loser]['rank'] = count($state->players);
            $state->events[] = ['type' => 'lose', 'seat' => $loser, 'amount' => count($state->players)];
            $state->status = CartaState::FINISHED;
            $state->currentSeat = null;
            $state->drawnCard = null;
        }
    }

    /** Every one of the 40 cards must exist exactly once across hands, draw pile and discard pile. */
    private function assertConserved(CartaState $state): void
    {
        $ids = $state->allCardIds();
        if (count($ids) !== 40 || count(array_unique($ids)) !== 40) {
            throw new LogicException('Card conservation violated.');
        }
    }
}
