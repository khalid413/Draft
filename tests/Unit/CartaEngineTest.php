<?php

namespace Tests\Unit;

use App\Carta\Card;
use App\Carta\CartaEngine;
use App\Carta\CartaState;
use DomainException;
use Tests\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

class CartaEngineTest extends TestCase
{
    private function engine(int $seed = 7): CartaEngine
    {
        return new CartaEngine(new Randomizer(new Mt19937($seed)));
    }

    /** Builds an exact table: given hands and top card; every other card goes to the draw pile (or $draw if given). */
    private function table(array $hands, string $top, int $current = 0, ?string $suit = null, ?array $draw = null): CartaState
    {
        $used = array_merge([$top], ...$hands);
        $rest = array_values(array_diff(array_map(fn (Card $card) => $card->id(), Card::deck()), $used));
        $discard = [];
        if ($draw !== null) {
            $discard = array_values(array_diff($rest, $draw));
            $rest = $draw;
        }
        $players = array_map(fn ($hand, $i) => ['name' => 'P'.$i, 'hand' => $hand, 'status' => CartaState::ACTIVE, 'rank' => null], $hands, array_keys($hands));

        return new CartaState(status: CartaState::PLAYING, players: $players, drawPile: $rest, discardPile: [...$discard, $top],
            currentSeat: $current, activeSuit: $suit ?? Card::fromId($top)->suit, turnNumber: 1);
    }

    private function assertConserved(CartaState $state): void
    {
        $ids = $state->allCardIds();
        $this->assertCount(40, $ids);
        $this->assertCount(40, array_unique($ids));
    }

    private function rejects(callable $move, string $message): void
    {
        try {
            $move();
            $this->fail('Expected the move to be rejected.');
        } catch (DomainException $error) {
            $this->assertStringContainsString($message, $error->getMessage());
        }
    }

    public function test_deck_has_forty_unique_cards_with_real_images_and_effects(): void
    {
        $deck = Card::deck();
        $this->assertCount(40, $deck);
        $this->assertCount(40, array_unique(array_map(fn (Card $card) => $card->id(), $deck)));
        foreach ($deck as $card) {
            $this->assertNotContains($card->rank, [8, 9]);
            $this->assertFileExists(__DIR__.'/../../public'.$card->image());
        }
        $this->assertFileExists(__DIR__.'/../../public'.Card::backImage());
        $this->assertSame(['skip', 'draw_two', 'change_suit', null], [Card::make('cups', 1)->effect(), Card::make('cups', 2)->effect(), Card::make('cups', 7)->effect(), Card::make('cups', 12)->effect()]);
        $this->assertSame('/cards/swords/swords_7.webp', Card::make('swords', 7)->image());
    }

    public function test_start_deals_five_each_turns_up_a_card_and_conserves_the_deck(): void
    {
        foreach ([2, 3, 6] as $count) {
            $state = $this->engine($count)->start(array_map(fn ($i) => 'P'.$i, range(1, $count)));
            $this->assertSame(CartaState::PLAYING, $state->status);
            foreach ($state->players as $player) {
                $this->assertCount(5, $player['hand']);
            }
            $this->assertCount(1, $state->discardPile);
            $this->assertCount(40 - 5 * $count - 1, $state->drawPile);
            $this->assertSame(CartaState::NONE, $state->pendingEffect, 'The starting card effect is ignored.');
            $this->assertSame($state->topCard()->suit, $state->activeSuit);
            $this->assertConserved($state);
        }
        $this->expectException(DomainException::class);
        $this->engine()->start(['Solo']);
    }

    public function test_cards_match_by_suit_or_number_and_illegal_or_out_of_turn_moves_are_rejected(): void
    {
        $state = $this->table([['coins_3', 'cups_5', 'clubs_12'], ['swords_4', 'swords_6']], 'coins_5');
        $engine = $this->engine();
        $this->assertEqualsCanonicalizing(['coins_3', 'cups_5'], $engine->legalCards($state, 0));
        $this->assertSame([], $engine->legalCards($state, 1));
        $this->rejects(fn () => $engine->play($state, 0, 'clubs_12'), 'does not match');
        $this->rejects(fn () => $engine->play($state, 1, 'swords_4'), 'not your turn');
        $this->rejects(fn () => $engine->play($state, 0, 'swords_4'), 'not in your hand');
        $engine->play($state, 0, 'cups_5');
        $this->assertSame('cups', $state->activeSuit);
        $this->assertSame(1, $state->currentSeat);
        $this->assertConserved($state);
    }

    public function test_skip_chain_transfers_and_only_the_last_target_loses_a_turn(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_1', 'cups_4'], ['swords_1', 'cups_5'], ['cups_1', 'cups_6'], ['clubs_1', 'clubs_3']], 'coins_5');
        $engine->play($state, 0, 'coins_1');
        $this->assertSame([CartaState::SKIP, 1], [$state->pendingEffect, $state->currentSeat]);
        $this->assertSame(['swords_1'], $engine->legalCards($state, 1), 'Only a 1 answers a skip.');
        $this->rejects(fn () => $engine->draw($state, 1), 'Answer the special card');
        $engine->play($state, 1, 'swords_1');
        $engine->play($state, 2, 'cups_1');
        $this->assertSame(3, $state->currentSeat);
        // D may accept even while holding a 1.
        $engine->accept($state, 3);
        $this->assertSame([CartaState::NONE, 0], [$state->pendingEffect, $state->currentSeat]);
        $this->assertCount(2, $state->players[3]['hand'], 'A skip never draws cards.');
        $this->assertSame('cups', $state->activeSuit);
        $this->assertConserved($state);
    }

    public function test_draw_two_stacks_add_two_each_and_the_target_draws_the_total(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_2', 'cups_4'], ['swords_2', 'cups_5'], ['cups_2', 'cups_6'], ['clubs_2', 'clubs_3', 'clubs_7']], 'coins_5');
        $engine->play($state, 0, 'coins_2');
        $engine->play($state, 1, 'swords_2');
        $engine->play($state, 2, 'cups_2');
        $this->assertSame([CartaState::DRAW_TWO, 6, 3], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->rejects(fn () => $engine->play($state, 3, 'clubs_7'), 'Answer with a 2');
        $engine->accept($state, 3);
        $this->assertCount(9, $state->players[3]['hand']);
        $this->assertSame([CartaState::NONE, 0, 0], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertConserved($state);
    }

    public function test_seven_is_wild_needs_a_valid_suit_and_cannot_interrupt_a_penalty(): void
    {
        $engine = $this->engine();
        $state = $this->table([['clubs_7', 'swords_7', 'cups_4'], ['cups_10', 'coins_10', 'swords_12']], 'coins_5');
        $this->rejects(fn () => $engine->play($state, 0, 'clubs_7'), 'Choose the next suit');
        $this->rejects(fn () => $engine->play($state, 0, 'clubs_7', 'moon'), 'Choose the next suit');
        $engine->play($state, 0, 'clubs_7', 'swords');
        $this->assertSame('swords', $state->activeSuit);
        $this->assertSame(['swords_12'], $engine->legalCards($state, 1), 'The chosen suit, not the 7\'s printed suit, now matches.');
        $engine->play($state, 1, 'swords_12');
        $this->assertContains('swords_7', $engine->legalCards($state, 0));

        $pending = $this->table([['coins_2', 'cups_4'], ['clubs_7', 'swords_2']], 'coins_5');
        $engine->play($pending, 0, 'coins_2');
        $this->assertSame(['swords_2'], $engine->legalCards($pending, 1));
        $this->rejects(fn () => $engine->play($pending, 1, 'clubs_7', 'cups'), 'Answer with a 2');
    }

    public function test_a_penalty_with_no_answer_card_applies_at_once(): void
    {
        $engine = $this->engine();
        // Seat 1 holds no 2: the +2 is drawn for them and the turn comes straight back.
        $state = $this->table([['coins_2', 'cups_4'], ['clubs_7', 'cups_5']], 'coins_5');
        $engine->play($state, 0, 'coins_2');
        $this->assertCount(4, $state->players[1]['hand']);
        $this->assertSame([CartaState::NONE, 0, 0], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertSame('accept_draw', end($state->events)['type']);

        // Seat 1 holds no 1: their turn is skipped without a click.
        $state = $this->table([['coins_1', 'cups_4'], ['clubs_7', 'cups_5'], ['coins_6', 'cups_6']], 'coins_5');
        $engine->play($state, 0, 'coins_1');
        $this->assertCount(2, $state->players[1]['hand'], 'A skip never draws cards.');
        $this->assertSame([CartaState::NONE, 2], [$state->pendingEffect, $state->currentSeat]);
        $this->assertSame('accept_skip', end($state->events)['type']);
        $this->assertConserved($state);
    }

    public function test_drawing_is_only_allowed_without_a_playable_card_and_a_playable_draw_may_be_played_or_passed(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_3', 'swords_4'], ['cups_6', 'cups_12']], 'coins_5', draw: ['clubs_4', 'coins_11']);
        $this->rejects(fn () => $engine->draw($state, 0), 'playable card');
        $engine->play($state, 0, 'coins_3');

        // Seat 1 has nothing for coins/3: draws coins_11 (last in pile) which is playable.
        $engine->draw($state, 1);
        $this->assertSame('coins_11', $state->drawnCard);
        $this->assertSame(1, $state->currentSeat);
        $this->assertSame(['coins_11'], $engine->legalCards($state, 1));
        $this->rejects(fn () => $engine->play($state, 1, 'cups_6'), 'only play the card you drew');
        $this->rejects(fn () => $engine->draw($state, 1), 'already drew');
        $engine->pass($state, 1);
        $this->assertSame([0, null], [$state->currentSeat, $state->drawnCard]);
        $this->rejects(fn () => $engine->pass($state, 0), 'only pass after drawing');

        // Seat 0 cannot match coins/3 with swords_4 and draws clubs_4, which does not match either, so the turn ends.
        $engine->draw($state, 0);
        $this->assertSame([1, null], [$state->currentSeat, $state->drawnCard]);
        $this->assertContains('clubs_4', $state->players[0]['hand']);
        $this->assertConserved($state);
    }

    public function test_reshuffle_keeps_the_top_card_and_never_duplicates_cards(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_2', 'cups_4'], ['swords_2', 'cups_5'], ['clubs_3', 'cups_6']], 'coins_5', draw: ['clubs_10']);
        $engine->play($state, 0, 'coins_2');
        $engine->play($state, 1, 'swords_2');    // Seat 2 holds no 2, so the +4 is drawn for them.
        $this->assertCount(6, $state->players[2]['hand']);
        $this->assertSame('swords_2', $state->topCard()->id(), 'The top card stays face-up after a reshuffle.');
        $this->assertConserved($state);
    }

    public function test_finishing_ranks_the_player_and_the_match_continues_without_them(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_3', 'cups_4'], ['coins_6'], ['coins_10', 'clubs_11']], 'coins_5');
        $engine->play($state, 0, 'coins_3');
        $engine->play($state, 1, 'coins_6');
        $this->assertSame([CartaState::DONE, 1], [$state->players[1]['status'], $state->players[1]['rank']]);
        $this->assertSame(CartaState::PLAYING, $state->status, 'The first finisher does not end the match.');
        $this->assertSame(2, $state->currentSeat);
        $engine->play($state, 2, 'coins_10');
        $this->assertSame(0, $state->currentSeat, 'Rotation skips the finished seat.');
        $this->rejects(fn () => $engine->draw($state, 1), 'finished');
        $this->assertSame([], $engine->legalCards($state, 1));
    }

    public function test_finishing_with_a_two_or_seven_cancels_its_effect_and_uses_the_printed_suit(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_2'], ['cups_4', 'clubs_5'], ['coins_10', 'clubs_11']], 'coins_5');
        $engine->play($state, 0, 'coins_2');
        $this->assertSame([CartaState::NONE, 0, 1], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertCount(2, $state->players[1]['hand'], 'The next player draws nothing.');

        $seven = $this->table([['clubs_7'], ['cups_4', 'clubs_5'], ['coins_10', 'clubs_11']], 'coins_5');
        $engine->play($seven, 0, 'clubs_7');
        $this->assertSame('clubs', $seven->activeSuit, 'A final 7 needs no suit and keeps its printed suit.');
        $this->assertSame(['clubs_5'], $engine->legalCards($seven, 1));
    }

    public function test_finishing_while_answering_a_chain_cancels_the_chain(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_1', 'cups_4'], ['swords_1'], ['clubs_3', 'clubs_12']], 'coins_5');
        $engine->play($state, 0, 'coins_1');
        $engine->play($state, 1, 'swords_1');
        $this->assertSame([CartaState::NONE, 2, 1], [$state->pendingEffect, $state->currentSeat, $state->players[1]['rank']]);
        $this->assertCount(2, $state->players[2]['hand']);
        $this->assertSame(['clubs_3', 'clubs_12'], $state->players[2]['hand']);
    }

    public function test_ranks_are_unique_and_the_last_active_player_is_the_loser(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_3', 'cups_7'], ['coins_6'], ['coins_10'], ['cups_11', 'clubs_12']], 'coins_5', current: 1, draw: ['swords_12', 'swords_11']);
        $engine->play($state, 1, 'coins_6');       // B finishes 1st
        $engine->play($state, 2, 'coins_10');      // C finishes 2nd
        $this->assertSame(CartaState::PLAYING, $state->status);
        $engine->draw($state, 3);                  // D cannot match coins/10 and draws swords_11: unplayable, turn ends
        $engine->play($state, 0, 'coins_3');
        $this->assertSame(3, $state->currentSeat, 'Only A and D rotate now.');
        $engine->draw($state, 3);                  // swords_12 does not match coins/3 either
        $engine->play($state, 0, 'cups_7');        // A finishes 3rd with a 7 (no suit needed); D is left holding cards
        $this->assertSame(CartaState::FINISHED, $state->status);
        $this->assertSame([2 => 1, 3 => 2, 1 => 3, 4 => 4], [2 => $state->players[1]['rank'], 3 => $state->players[2]['rank'], 1 => $state->players[0]['rank'], 4 => $state->players[3]['rank']]);
        $this->assertSame(CartaState::LOSER, $state->players[3]['status']);
        $this->assertNull($state->currentSeat);
        $this->rejects(fn () => $engine->draw($state, 3), 'not running');
        $this->assertConserved($state);
    }

    public function test_two_players_the_first_to_empty_their_hand_wins_and_the_other_loses(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_3'], ['cups_4', 'clubs_5']], 'coins_5');
        $engine->play($state, 0, 'coins_3');
        $this->assertSame(CartaState::FINISHED, $state->status);
        $this->assertSame([1, CartaState::DONE], [$state->players[0]['rank'], $state->players[0]['status']]);
        $this->assertSame([2, CartaState::LOSER], [$state->players[1]['rank'], $state->players[1]['status']]);
    }

    public function test_hundreds_of_automatic_matches_always_end_with_one_loser_and_conserved_cards(): void
    {
        for ($seed = 1; $seed <= 300; $seed++) {
            $engine = $this->engine($seed);
            $players = 2 + $seed % 5;
            $state = $engine->start(array_map(fn ($i) => 'P'.$i, range(1, $players)));
            for ($turns = 0; $state->status === CartaState::PLAYING && $turns < 5000; $turns++) {
                $engine->autoAct($state);
                $this->assertConserved($state);
            }
            $this->assertSame(CartaState::FINISHED, $state->status, "Seed $seed did not finish.");
            $ranks = array_column($state->players, 'rank');
            sort($ranks);
            $this->assertSame(range(1, $players), $ranks);
            $losers = array_filter($state->players, fn ($player) => $player['status'] === CartaState::LOSER);
            $this->assertCount(1, $losers);
            $this->assertSame($players, reset($losers)['rank']);
        }
    }

    public function test_when_the_only_opponent_leaves_the_remaining_player_wins(): void
    {
        $engine = $this->engine();
        $state = $this->table([['coins_3', 'cups_4'], ['swords_5', 'clubs_6']], 'coins_2', current: 0);
        $state->pendingEffect = CartaState::DRAW_TWO;
        $state->pendingDraw = 2;
        $engine->forfeit($state, 1);
        $this->assertSame(CartaState::FINISHED, $state->status);
        $this->assertSame([1, CartaState::DONE], [$state->players[0]['rank'], $state->players[0]['status']]);
        $this->assertSame([2, CartaState::LOSER, []], [$state->players[1]['rank'], $state->players[1]['status'], $state->players[1]['hand']]);
        $this->assertSame([null, CartaState::NONE], [$state->currentSeat, $state->pendingEffect]);
        $this->assertConserved($state);
    }

    public function test_a_forfeit_ranks_last_and_the_match_goes_on_without_that_seat(): void
    {
        $engine = $this->engine();
        // P1 leaves on their own turn with a +2 aimed at them: the penalty is dropped and play moves to P2.
        $state = $this->table([['cups_3'], ['swords_5', 'clubs_6'], ['cups_4', 'coins_6']], 'cups_2', current: 1);
        $state->pendingEffect = CartaState::DRAW_TWO;
        $state->pendingDraw = 2;
        $engine->forfeit($state, 1);
        $this->assertSame(CartaState::PLAYING, $state->status);
        $this->assertSame([3, CartaState::LOSER], [$state->players[1]['rank'], $state->players[1]['status']]);
        $this->assertSame([2, CartaState::NONE], [$state->currentSeat, $state->pendingEffect]);
        $this->assertConserved($state);

        // P2 ends their turn on a legal card, then P0 empties their hand: 1st, and P2 is the loser in 2nd.
        $engine->play($state, 2, 'cups_4');
        $engine->play($state, 0, 'cups_3');
        $this->assertSame(CartaState::FINISHED, $state->status);
        $this->assertSame([1, 2, 3], [$state->players[0]['rank'], $state->players[2]['rank'], $state->players[1]['rank']]);
        $this->assertSame(CartaState::LOSER, $state->players[2]['status']);
        $this->assertConserved($state);
    }
}
