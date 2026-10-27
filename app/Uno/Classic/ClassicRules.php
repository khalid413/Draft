<?php

namespace App\Uno\Classic;

use App\Uno\UnoCard;
use App\Uno\UnoRules;
use App\Uno\UnoState;
use DomainException;
use LogicException;
use Random\Randomizer;

/**
 * UNO Classic rules with Glisa's ranking: players leave the rotation as they empty their hands,
 * and the last one holding cards loses. Every method validates the move, mutates the state,
 * and records public history in $state->events.
 */
final class ClassicRules implements UnoRules
{
    public const UNO_PENALTY = 2;

    public const CHALLENGE_EXTRA = 2;

    private Randomizer $random;

    public function __construct(private ClassicVariant $variant, ?Randomizer $random = null)
    {
        $this->random = $random ?? new Randomizer;
    }

    /** Shuffles the 108 cards, deals seven each and turns up a number card to start (action cards go back into the deck). */
    public function start(array $names, bool $stacking, ?int $startingSeat = null): UnoState
    {
        $names = array_values($names);
        if (count($names) < 2 || count($names) > 6) {
            throw new DomainException(__('UNO needs 2 to 6 players.'));
        }
        $pile = $this->random->shuffleArray($this->variant->deck());
        $players = array_map(fn ($name) => ['name' => $name, 'hand' => [], 'status' => UnoState::ACTIVE, 'rank' => null, 'uno' => false], $names);
        for ($round = 0; $round < $this->variant->handSize(); $round++) {
            foreach (array_keys($players) as $seat) {
                $players[$seat]['hand'][] = array_pop($pile);
            }
        }
        while (! UnoCard::fromId(end($pile))->isNumber()) {
            $pile = $this->random->shuffleArray($pile);
        }
        $starter = array_pop($pile);
        $state = new UnoState(status: UnoState::PLAYING, players: $players, drawPile: $pile, discardPile: [$starter],
            currentSeat: $startingSeat ?? $this->random->getInt(0, count($players) - 1), activeColor: UnoCard::fromId($starter)->color,
            stacking: $stacking, turnNumber: 1);
        $state->events[] = ['type' => 'deal', 'seat' => null, 'card' => $starter, 'amount' => $this->variant->handSize()];
        $this->assertConserved($state);

        return $state;
    }

    public function legalCards(UnoState $state, int $seat): array
    {
        if ($state->status !== UnoState::PLAYING || $state->currentSeat !== $seat || ! $state->isActive($seat)) {
            return [];
        }
        $candidates = $state->drawnCard !== null ? [$state->drawnCard] : $state->players[$seat]['hand'];

        return array_values(array_filter($candidates, fn ($id) => $this->canPlay($state, UnoCard::fromId($id))));
    }

    private function canPlay(UnoState $state, UnoCard $card): bool
    {
        return match ($state->pendingEffect) {
            // Only reachable with stacking on: without it a penalty is resolved before the target gets to act.
            UnoState::DRAW_TWO => $state->stacking && $card->value === 'draw2',
            UnoState::DRAW_FOUR => $state->stacking && $card->value === 'wild4',
            default => $card->isWild() || $card->color === $state->activeColor || $card->value === $state->topCard()->value,
        };
    }

    public function play(UnoState $state, int $seat, string $cardId, ?string $color = null): UnoState
    {
        $this->assertTurn($state, $seat);
        $card = UnoCard::fromId($cardId);
        $hand = $state->players[$seat]['hand'];
        $position = array_search($cardId, $hand, true);
        if ($position === false) {
            throw new DomainException(__('That card is not in your hand.'));
        }
        if ($state->drawnCard !== null && $state->drawnCard !== $cardId) {
            throw new DomainException(__('After drawing, only the card you drew can be played.'));
        }
        if (! $this->canPlay($state, $card)) {
            throw new DomainException(match ($state->pendingEffect) {
                UnoState::DRAW_TWO => $state->stacking ? __('Stack a +2, or take the penalty.') : __('Take the penalty cards.'),
                UnoState::DRAW_FOUR => $state->stacking ? __('Stack a +4, challenge it, or take the penalty.') : __('Challenge the +4, or take the penalty.'),
                default => __('That card does not match the colour, number or symbol on the table.'),
            });
        }
        $finishing = count($hand) === 1;
        if ($card->isWild() && ! $finishing && ! UnoCard::colorIsValid($color)) {
            throw new DomainException(__('Choose the next colour for your wild card.'));
        }
        // A Wild Draw Four is only legal without a card of the current colour; it can still be played as a bluff and challenged.
        $legalFour = $card->value !== 'wild4' || ! array_filter($hand, fn ($id) => UnoCard::fromId($id)->color === $state->activeColor);
        $this->closeUnoWindow($state, $seat);

        unset($hand[$position]);
        $state->players[$seat]['hand'] = array_values($hand);
        $state->discardPile[] = $cardId;
        $state->drawnCard = null;
        $state->events[] = ['type' => 'play', 'seat' => $seat, 'card' => $cardId, 'color' => $card->isWild() && ! $finishing ? $color : null];

        if ($finishing) {
            // The last card ends this player's game: its effect, and any penalty chain it answered, is cancelled.
            $state->activeColor = $card->color ?? $state->activeColor;
            $state->pendingEffect = UnoState::NONE;
            $state->pendingDraw = 0;
            $state->challenge = null;
            $this->finishPlayer($state, $seat);
            if ($state->status === UnoState::PLAYING) {
                $this->advance($state, $seat);
            }
            $this->assertConserved($state);

            return $state;
        }

        $this->syncUno($state, $seat, afterPlay: true);
        $state->activeColor = $card->isWild() ? $color : $card->color;
        switch ($card->value) {
            case 'skip':
                $this->advance($state, $seat, 2);
                break;
            case 'reverse':
                $state->direction *= -1;
                // With two players a Reverse works like a Skip: the same player goes again.
                $this->advance($state, $seat, count($state->activeSeats()) === 2 ? 2 : 1);
                break;
            case 'draw2':
                $state->pendingEffect = UnoState::DRAW_TWO;
                $state->pendingDraw += 2;
                $this->advance($state, $seat);
                // Without stacking, or without a +2 to stack, there is nothing to decide: the penalty applies at once.
                if (! $this->legalCards($state, $state->currentSeat)) {
                    $this->takePenalty($state, $state->currentSeat);
                }
                break;
            case 'wild4':
                $state->pendingEffect = UnoState::DRAW_FOUR;
                $state->pendingDraw += 4;
                $this->advance($state, $seat);
                // The target always gets a choice, because they may challenge.
                $state->challenge = ['by' => $seat, 'target' => $state->currentSeat, 'legal' => $legalFour];
                break;
            default:
                $this->advance($state, $seat);
        }
        $this->assertConserved($state);

        return $state;
    }

    /** Allowed whenever no penalty is waiting, even holding a playable card. Only the drawn card may then be played. */
    public function draw(UnoState $state, int $seat): UnoState
    {
        $this->assertTurn($state, $seat);
        if ($state->pendingEffect !== UnoState::NONE) {
            throw new DomainException(__('Answer the penalty first.'));
        }
        if ($state->drawnCard !== null) {
            throw new DomainException(__('You already drew this turn. Play that card or pass.'));
        }
        $this->closeUnoWindow($state, $seat);
        $cards = $this->take($state, 1);
        $state->players[$seat]['hand'] = [...$state->players[$seat]['hand'], ...$cards];
        $state->events[] = ['type' => 'draw', 'seat' => $seat, 'card' => $cards[0] ?? null, 'amount' => count($cards)];
        $this->syncUno($state, $seat);
        if ($cards && $this->canPlay($state, UnoCard::fromId($cards[0]))) {
            $state->drawnCard = $cards[0];
        } else {
            $this->advance($state, $seat);
        }
        $this->assertConserved($state);

        return $state;
    }

    /** Keeps the playable card just drawn and ends the turn. */
    public function pass(UnoState $state, int $seat): UnoState
    {
        $this->assertTurn($state, $seat);
        if ($state->drawnCard === null) {
            throw new DomainException(__('You can only pass after drawing a playable card.'));
        }
        $this->closeUnoWindow($state, $seat);
        $state->drawnCard = null;
        $state->events[] = ['type' => 'pass', 'seat' => $seat];
        $this->advance($state, $seat);

        return $state;
    }

    /** Takes the whole pending penalty and loses the turn. */
    public function accept(UnoState $state, int $seat): UnoState
    {
        $this->assertTurn($state, $seat);
        if ($state->pendingEffect === UnoState::NONE) {
            throw new DomainException(__('There is no penalty to take.'));
        }
        $this->closeUnoWindow($state, $seat);
        $this->takePenalty($state, $seat);
        $this->assertConserved($state);

        return $state;
    }

    /**
     * Challenges the Wild Draw Four aimed at this seat. If it was played while holding the active colour,
     * its player takes the penalty and the challenger plays on; otherwise the challenger takes it plus two and loses the turn.
     */
    public function challenge(UnoState $state, int $seat): UnoState
    {
        $this->assertTurn($state, $seat);
        $challenge = $state->challenge;
        if ($state->pendingEffect !== UnoState::DRAW_FOUR || ! $challenge || $challenge['target'] !== $seat) {
            throw new DomainException(__('There is no +4 to challenge.'));
        }
        $this->closeUnoWindow($state, $seat);
        $penalty = $state->pendingDraw;
        $state->pendingEffect = UnoState::NONE;
        $state->pendingDraw = 0;
        $state->challenge = null;
        if (! $challenge['legal']) {
            $cards = $this->take($state, $penalty);
            $state->players[$challenge['by']]['hand'] = [...$state->players[$challenge['by']]['hand'], ...$cards];
            $this->syncUno($state, $challenge['by']);
            $state->events[] = ['type' => 'challenge_won', 'seat' => $seat, 'target' => $challenge['by'], 'amount' => count($cards)];
        } else {
            $cards = $this->take($state, $penalty + self::CHALLENGE_EXTRA);
            $state->players[$seat]['hand'] = [...$state->players[$seat]['hand'], ...$cards];
            $this->syncUno($state, $seat);
            $state->events[] = ['type' => 'challenge_lost', 'seat' => $seat, 'target' => $challenge['by'], 'amount' => count($cards)];
            $this->advance($state, $seat);
        }
        $this->assertConserved($state);

        return $state;
    }

    /** True on your turn with two cards and one of them playable, or right after you were left on one card without calling. */
    public function canCallUno(UnoState $state, int $seat): bool
    {
        if ($state->status !== UnoState::PLAYING || ! $state->isActive($seat)) {
            return false;
        }
        if ($state->unoExposed === $seat) {
            return true;
        }

        return ! $state->players[$seat]['uno'] && count($state->players[$seat]['hand']) === 2 && $this->legalCards($state, $seat);
    }

    public function callUno(UnoState $state, int $seat): UnoState
    {
        if (! $this->canCallUno($state, $seat)) {
            throw new DomainException(__('Call UNO when you are about to play down to one card.'));
        }
        $state->players[$seat]['uno'] = true;
        $state->unoExposed = null;
        $state->events[] = ['type' => 'uno', 'seat' => $seat];

        return $state;
    }

    /** Any other active player may catch a missed UNO until the next move is made. */
    public function catchUno(UnoState $state, int $catcher, int $target): UnoState
    {
        if ($state->status !== UnoState::PLAYING || ! $state->isActive($catcher)) {
            throw new DomainException(__('Only players still in the match can catch.'));
        }
        if ($catcher === $target || $state->unoExposed !== $target) {
            throw new DomainException(__('Too late: that player is safe.'));
        }
        $cards = $this->take($state, self::UNO_PENALTY);
        $state->players[$target]['hand'] = [...$state->players[$target]['hand'], ...$cards];
        $state->unoExposed = null;
        $this->syncUno($state, $target);
        $state->events[] = ['type' => 'caught', 'seat' => $target, 'target' => $catcher, 'amount' => count($cards)];
        $this->assertConserved($state);

        return $state;
    }

    /**
     * A player who leaves mid-match forfeits: ranked last among those still playing, their cards return under the draw pile.
     * Any penalty aimed at them is dropped. When one player remains, they finish next and the match ends.
     */
    public function forfeit(UnoState $state, int $seat): UnoState
    {
        if ($state->status !== UnoState::PLAYING || ! $state->isActive($seat)) {
            return $state;
        }
        $forfeited = count(array_filter($state->players, fn ($player) => $player['status'] === UnoState::LOSER));
        $state->drawPile = [...$state->players[$seat]['hand'], ...$state->drawPile];
        $state->players[$seat]['hand'] = [];
        $state->players[$seat]['status'] = UnoState::LOSER;
        $state->players[$seat]['rank'] = count($state->players) - $forfeited;
        $state->players[$seat]['uno'] = false;
        $state->events[] = ['type' => 'forfeit', 'seat' => $seat, 'amount' => $state->players[$seat]['rank']];
        if ($state->unoExposed === $seat) {
            $state->unoExposed = null;
        }
        if (($state->challenge['by'] ?? null) === $seat) {
            $state->challenge = null; // Nobody left to answer for that +4; it can still be taken or stacked.
        }

        $active = $state->activeSeats();
        if (count($active) === 1) {
            $winner = $active[0];
            $rank = count(array_filter($state->players, fn ($player) => $player['status'] === UnoState::DONE)) + 1;
            $state->players[$winner]['status'] = UnoState::DONE;
            $state->players[$winner]['rank'] = $rank;
            $state->events[] = ['type' => 'finish', 'seat' => $winner, 'amount' => $rank];
            $this->endMatch($state);
        } elseif ($state->currentSeat === $seat) {
            $state->pendingEffect = UnoState::NONE;
            $state->pendingDraw = 0;
            $state->challenge = null;
            $state->drawnCard = null;
            $this->advance($state, $seat);
        }
        $this->assertConserved($state);

        return $state;
    }

    /** Plays the current turn for whoever's turn it is; used to simulate whole matches. */
    public function autoAct(UnoState $state): UnoState
    {
        $seat = $state->currentSeat;
        $legal = $this->legalCards($state, $seat);
        if ($legal && $this->canCallUno($state, $seat)) {
            $this->callUno($state, $seat);
        }
        if ($legal) {
            $card = UnoCard::fromId($legal[0]);

            return $this->play($state, $seat, $legal[0], $card->isWild() ? $this->favouriteColor($state, $seat, $card) : null);
        }
        if ($state->drawnCard !== null) {
            return $this->pass($state, $seat);
        }
        if ($state->pendingEffect !== UnoState::NONE) {
            return $this->accept($state, $seat);
        }

        return $this->draw($state, $seat);
    }

    private function favouriteColor(UnoState $state, int $seat, UnoCard $playing): string
    {
        $counts = array_count_values(array_filter(array_map(fn ($id) => UnoCard::fromId($id)->color,
            array_filter($state->players[$seat]['hand'], fn ($id) => $id !== $playing->id()))));
        arsort($counts);

        return array_key_first($counts) ?? UnoCard::COLORS[0];
    }

    private function takePenalty(UnoState $state, int $seat): void
    {
        $cards = $this->take($state, $state->pendingDraw);
        $state->players[$seat]['hand'] = [...$state->players[$seat]['hand'], ...$cards];
        $state->events[] = ['type' => 'accept_draw', 'seat' => $seat, 'amount' => count($cards)];
        $this->syncUno($state, $seat);
        $state->pendingEffect = UnoState::NONE;
        $state->pendingDraw = 0;
        $state->challenge = null;
        $this->advance($state, $seat);
    }

    /**
     * Keeps the UNO flags honest after a hand changes: an UNO call only covers a hand of one card,
     * and a player who just played down to one card without calling becomes catchable.
     */
    private function syncUno(UnoState $state, int $seat, bool $afterPlay = false): void
    {
        $single = count($state->players[$seat]['hand']) === 1;
        if ($afterPlay && $single && ! $state->players[$seat]['uno']) {
            $state->unoExposed = $seat;
        }
        if (! $single) {
            $state->players[$seat]['uno'] = false;
            if ($state->unoExposed === $seat) {
                $state->unoExposed = null;
            }
        }
    }

    /** A missed UNO can only be caught before the next player acts. */
    private function closeUnoWindow(UnoState $state, int $actor): void
    {
        if ($state->unoExposed !== null && $state->unoExposed !== $actor) {
            $state->unoExposed = null;
        }
    }

    private function assertTurn(UnoState $state, int $seat): void
    {
        if ($state->status !== UnoState::PLAYING) {
            throw new DomainException(__('This match is not running.'));
        }
        if (! isset($state->players[$seat])) {
            throw new DomainException(__('You are not seated at this table.'));
        }
        if (! $state->isActive($seat)) {
            throw new DomainException(__('You have finished this match. Watch the others play it out.'));
        }
        if ($state->currentSeat !== $seat) {
            throw new DomainException(__('It is not your turn.'));
        }
    }

    /** Next seat in the current direction that still holds cards; finished players are skipped entirely. */
    private function nextActiveSeat(UnoState $state, int $from): int
    {
        $count = count($state->players);
        for ($step = 1; $step <= $count; $step++) {
            $seat = (($from + $state->direction * $step) % $count + $count) % $count;
            if ($state->isActive($seat)) {
                return $seat;
            }
        }
        throw new LogicException('No active player left.');
    }

    /** Moves the turn on by $steps active seats (2 skips the next player). */
    private function advance(UnoState $state, int $from, int $steps = 1): void
    {
        $seat = $from;
        for ($i = 0; $i < $steps; $i++) {
            $seat = $this->nextActiveSeat($state, $seat);
        }
        $state->currentSeat = $seat;
        $state->turnNumber++;
    }

    /** Draws up to $count cards, reshuffling the discard pile (keeping its top card) when the draw pile runs out. */
    private function take(UnoState $state, int $count): array
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
    private function finishPlayer(UnoState $state, int $seat): void
    {
        // Finishers rank from the top; players who forfeited already hold the bottom ranks.
        $rank = count(array_filter($state->players, fn ($player) => $player['status'] === UnoState::DONE)) + 1;
        $state->players[$seat]['status'] = UnoState::DONE;
        $state->players[$seat]['rank'] = $rank;
        $state->players[$seat]['uno'] = false;
        $state->events[] = ['type' => 'finish', 'seat' => $seat, 'amount' => $rank];
        $active = $state->activeSeats();
        if (count($active) === 1) {
            $loser = $active[0];
            $loserRank = count($state->players) - count(array_filter($state->players, fn ($player) => $player['status'] === UnoState::LOSER));
            $state->players[$loser]['status'] = UnoState::LOSER;
            $state->players[$loser]['rank'] = $loserRank;
            $state->events[] = ['type' => 'lose', 'seat' => $loser, 'amount' => $loserRank];
            $this->endMatch($state);
        }
    }

    private function endMatch(UnoState $state): void
    {
        $state->status = UnoState::FINISHED;
        $state->currentSeat = null;
        $state->pendingEffect = UnoState::NONE;
        $state->pendingDraw = 0;
        $state->drawnCard = null;
        $state->challenge = null;
        $state->unoExposed = null;
    }

    /** Every physical card of the deck must exist exactly once across hands, draw pile and discard pile. */
    private function assertConserved(UnoState $state): void
    {
        $ids = $state->allCardIds();
        $deck = $this->variant->deck();
        if (count($ids) !== count($deck) || array_diff($deck, $ids) || count(array_unique($ids)) !== count($ids)) {
            throw new LogicException('Card conservation violated.');
        }
    }
}
