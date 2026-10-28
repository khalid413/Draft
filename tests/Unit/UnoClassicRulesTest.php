<?php

namespace Tests\Unit;

use App\Uno\Classic\ClassicRules;
use App\Uno\Classic\ClassicVariant;
use App\Uno\UnoCard;
use App\Uno\UnoState;
use App\Uno\UnoVariants;
use DomainException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

class UnoClassicRulesTest extends TestCase
{
    private function rules(int $seed = 7): ClassicRules
    {
        return new ClassicRules(new ClassicVariant, new Randomizer(new Mt19937($seed)));
    }

    /** Builds an exact table: given hands and top card; every other card goes to the draw pile (or $draw, last drawn first). */
    private function table(array $hands, string $top, int $current = 0, ?string $color = null, string $stacking = UnoState::STACK_OFF, ?array $draw = null, int $direction = 1): UnoState
    {
        $used = array_merge([$top], ...$hands);
        $rest = array_values(array_diff((new ClassicVariant)->deck(), $used));
        $discard = [];
        if ($draw !== null) {
            $discard = array_values(array_diff($rest, $draw));
            $rest = $draw;
        }
        $players = array_map(fn ($hand, $i) => ['name' => 'P'.$i, 'hand' => $hand, 'status' => UnoState::ACTIVE, 'rank' => null, 'uno' => false], $hands, array_keys($hands));

        return new UnoState(status: UnoState::PLAYING, players: $players, drawPile: $rest, discardPile: [...$discard, $top],
            currentSeat: $current, direction: $direction, activeColor: $color ?? UnoCard::fromId($top)->color, stacking: $stacking, turnNumber: 1);
    }

    private function assertConserved(UnoState $state): void
    {
        $ids = $state->allCardIds();
        $this->assertCount(108, $ids);
        $this->assertCount(108, array_unique($ids));
        $this->assertEqualsCanonicalizing((new ClassicVariant)->deck(), $ids);
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

    public function test_classic_deck_has_the_108_official_cards_with_unique_ids(): void
    {
        $deck = (new ClassicVariant)->deck();
        $this->assertCount(108, $deck);
        $this->assertCount(108, array_unique($deck));
        $faces = array_count_values(array_map(fn ($id) => UnoCard::fromId($id)->face(), $deck));
        foreach (UnoCard::COLORS as $color) {
            $this->assertSame(1, $faces[$color.'_0']);
            foreach ([...range(1, 9), 'skip', 'reverse', 'draw2'] as $value) {
                $this->assertSame(2, $faces[$color.'_'.$value], "$color $value");
            }
        }
        $this->assertSame(4, $faces['wild']);
        $this->assertSame(4, $faces['wild4']);
        $this->assertCount(25 * 4, array_filter($deck, fn ($id) => UnoCard::fromId($id)->color !== null));
    }

    public function test_every_card_uses_one_of_the_72_supplied_faces_and_every_face_is_used(): void
    {
        $variant = new ClassicVariant;
        $public = __DIR__.'/../../public';
        $images = array_map(fn ($id) => $variant->image(UnoCard::fromId($id)), $variant->deck());
        foreach (array_unique($images) as $image) {
            $this->assertFileExists($public.$image);
        }
        $files = array_map(fn ($path) => '/cards/uno/classic/'.basename($path), glob($public.'/cards/uno/classic/*.png'));
        $this->assertCount(72, $files);
        $this->assertEqualsCanonicalizing($files, array_values(array_unique($images)), 'The 108 cards map onto exactly the 72 faces.');
        $this->assertSame('/cards/uno/classic/red_7.png', $variant->image(UnoCard::fromId('red_7_2')));
        $this->assertSame('/cards/uno/classic/red_skip_2.png', $variant->image(UnoCard::fromId('red_skip_2')));
        $this->assertSame('/cards/uno/classic/wild4_3.png', $variant->image(UnoCard::fromId('wild4_3')));
        $this->assertFileExists($public.$variant->backImage());
        $this->assertSame(['classic'], UnoVariants::available());
    }

    public function test_start_deals_seven_each_turns_up_a_number_and_conserves_the_deck(): void
    {
        foreach ([2, 4, 6] as $count) {
            foreach (range(1, 15) as $seed) {
                $state = $this->rules($seed * $count)->start(array_map(fn ($i) => 'P'.$i, range(1, $count)), UnoState::STACK_OFF);
                foreach ($state->players as $player) {
                    $this->assertCount(7, $player['hand']);
                }
                $this->assertTrue($state->topCard()->isNumber());
                $this->assertSame($state->topCard()->color, $state->activeColor);
                $this->assertCount(108 - 7 * $count - 1, $state->drawPile);
                $this->assertConserved($state);
            }
        }
    }

    public function test_cards_match_by_colour_number_or_symbol_and_wilds_always_play(): void
    {
        $state = $this->table([['red_3_1', 'blue_5_1', 'green_skip_1', 'yellow_9_1', 'wild_1'], ['green_1_1']], 'blue_skip_2');
        $this->assertEqualsCanonicalizing(['blue_5_1', 'green_skip_1', 'wild_1'], $this->rules()->legalCards($state, 0));
        $this->assertSame([], $this->rules()->legalCards($state, 1), 'Not their turn.');
        $this->rejects(fn () => $this->rules()->play($state, 0, 'red_3_1'), 'does not match');
        $this->rejects(fn () => $this->rules()->play($state, 1, 'green_1_1'), 'not your turn');
        $this->rejects(fn () => $this->rules()->play($state, 0, 'red_4_1'), 'not in your hand');
        $this->rejects(fn () => $this->rules()->play($state, 0, 'wild_1'), 'Choose the next colour');

        $state = $this->table([['red_3_1', 'blue_3_2', 'green_8_1'], ['green_1_1']], 'yellow_3_1');
        $this->assertEqualsCanonicalizing(['red_3_1', 'blue_3_2'], $this->rules()->legalCards($state, 0));
        $this->rules()->play($state, 0, 'blue_3_2');
        $this->assertSame('blue', $state->activeColor);
        $this->assertSame(1, $state->currentSeat);

        // After a wild, the chosen colour is what counts.
        $state = $this->table([['wild_2', 'red_1_1'], ['green_4_1', 'red_6_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'wild_2', 'green');
        $this->assertSame('green', $state->activeColor);
        $this->assertSame(['green_4_1'], $this->rules()->legalCards($state, 1));
        $this->assertConserved($state);
    }

    public function test_skip_and_reverse_with_three_and_with_two_players(): void
    {
        $state = $this->table([['red_skip_1', 'red_1_1'], ['red_2_1'], ['red_3_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_skip_1');
        $this->assertSame(2, $state->currentSeat, 'Seat 1 is skipped.');

        $state = $this->table([['red_reverse_1', 'red_1_1'], ['red_2_1'], ['red_3_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_reverse_1');
        $this->assertSame(-1, $state->direction);
        $this->assertSame(2, $state->currentSeat, 'Play now runs the other way.');
        $this->rules()->play($state, 2, 'red_3_1');
        $this->assertSame(1, $state->currentSeat);

        $state = $this->table([['red_reverse_1', 'red_1_1'], ['red_2_1', 'blue_2_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_reverse_1');
        $this->assertSame(0, $state->currentSeat, 'With two players a Reverse acts like a Skip.');
        $state = $this->table([['red_skip_1', 'red_1_1'], ['red_2_1', 'blue_2_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_skip_1');
        $this->assertSame(0, $state->currentSeat);
    }

    public function test_official_mode_applies_plus_two_and_plus_four_at_once(): void
    {
        $state = $this->table([['red_draw2_1', 'red_1_1'], ['blue_draw2_1', 'green_5_1'], ['red_3_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_draw2_1');
        $this->assertCount(4, $state->players[1]['hand'], 'Seat 1 drew two even though it holds a +2.');
        $this->assertSame(2, $state->currentSeat, 'And lost the turn.');
        $this->assertSame(UnoState::NONE, $state->pendingEffect);

        // A +4 can be played any time (no colour restriction, no challenge) and lands at once.
        $state = $this->table([['wild4_1', 'red_1_1'], ['wild4_2', 'green_5_1'], ['red_3_1']], 'red_9_1');
        $this->assertContains('wild4_1', $this->rules()->legalCards($state, 0), 'Holding red does not block the +4.');
        $this->rules()->play($state, 0, 'wild4_1', 'yellow');
        $this->assertCount(6, $state->players[1]['hand']);
        $this->assertSame([UnoState::NONE, 0, 2], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertSame('yellow', $state->activeColor);
        $this->assertConserved($state);
    }

    public function test_same_card_stacking_adds_up_and_never_mixes_two_and_four(): void
    {
        $state = $this->table([['red_draw2_1', 'red_1_1'], ['blue_draw2_1', 'wild4_1', 'green_5_1'], ['yellow_draw2_2', 'red_3_1'], ['green_6_1']], 'red_9_1', stacking: UnoState::STACK_SAME);
        $this->rules()->play($state, 0, 'red_draw2_1');
        $this->assertSame([UnoState::DRAW_TWO, 2, 1], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertSame(['blue_draw2_1'], $this->rules()->legalCards($state, 1), 'A +4 cannot answer a +2.');
        $this->rejects(fn () => $this->rules()->draw($state, 1), 'Answer the penalty');
        $this->rules()->play($state, 1, 'blue_draw2_1');
        $this->assertSame(4, $state->pendingDraw);
        $this->rules()->play($state, 2, 'yellow_draw2_2');
        // Seat 3 has no +2: the six cards land at once.
        $this->assertCount(7, $state->players[3]['hand']);
        $this->assertSame([UnoState::NONE, 0, 0], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertConserved($state);

        $state = $this->table([['wild4_1', 'blue_1_1'], ['wild4_2', 'red_draw2_1', 'green_5_1'], ['red_3_1', 'red_4_1', 'wild4_3']], 'red_9_1', stacking: UnoState::STACK_SAME);
        $this->rules()->play($state, 0, 'wild4_1', 'blue');
        $this->assertSame(['wild4_2'], $this->rules()->legalCards($state, 1), 'A +2 cannot answer a +4.');
        $this->rules()->play($state, 1, 'wild4_2', 'green');
        $this->assertSame([UnoState::DRAW_FOUR, 8, 2], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->rules()->accept($state, 2);
        $this->assertCount(11, $state->players[2]['hand']);
        $this->assertSame(0, $state->currentSeat, 'The target chose to take eight and lost the turn.');
        $this->assertSame('green', $state->activeColor);
        $this->assertConserved($state);
    }

    public function test_mixed_stacking_lets_plus_two_and_plus_four_answer_each_other(): void
    {
        $state = $this->table([['red_draw2_1', 'red_1_1'], ['wild4_1', 'green_5_1'], ['blue_draw2_1', 'red_3_1'], ['green_6_1']], 'red_9_1', stacking: UnoState::STACK_MIXED);
        $this->rules()->play($state, 0, 'red_draw2_1');
        $this->assertSame(['wild4_1'], $this->rules()->legalCards($state, 1), 'A +4 answers a +2.');
        $this->rules()->play($state, 1, 'wild4_1', 'blue');
        $this->assertSame([UnoState::DRAW_FOUR, 6, 2], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertSame(['blue_draw2_1'], $this->rules()->legalCards($state, 2), 'A +2 answers a +4.');
        $this->rejects(fn () => $this->rules()->play($state, 2, 'red_3_1'), 'Stack a +2 or a +4');
        $this->rules()->play($state, 2, 'blue_draw2_1');
        // Seat 3 holds neither: all eight cards land at once and play moves on.
        $this->assertCount(9, $state->players[3]['hand']);
        $this->assertSame([UnoState::NONE, 0, 0], [$state->pendingEffect, $state->pendingDraw, $state->currentSeat]);
        $this->assertConserved($state);
    }


    public function test_drawing_keeps_the_turn_only_for_a_playable_drawn_card(): void
    {
        // Voluntary draw while holding a playable card; the drawn card is playable.
        $state = $this->table([['red_1_1', 'blue_2_1'], ['green_3_1']], 'red_9_1', draw: ['yellow_5_1', 'red_8_1']);
        $this->rules()->draw($state, 0);
        $this->assertSame('red_8_1', $state->drawnCard);
        $this->assertSame(0, $state->currentSeat);
        $this->assertSame(['red_8_1'], $this->rules()->legalCards($state, 0), 'Only the drawn card may be played.');
        $this->rejects(fn () => $this->rules()->play($state, 0, 'red_1_1'), 'only the card you drew');
        $this->rejects(fn () => $this->rules()->draw($state, 0), 'already drew');
        $this->rules()->pass($state, 0);
        $this->assertSame([null, 1], [$state->drawnCard, $state->currentSeat]);
        $this->assertCount(3, $state->players[0]['hand']);

        // A drawn card that cannot be played ends the turn at once.
        $state = $this->table([['blue_2_1'], ['green_3_1']], 'red_9_1', draw: ['yellow_5_1']);
        $this->rules()->draw($state, 0);
        $this->assertSame([null, 1], [$state->drawnCard, $state->currentSeat]);
        $this->rejects(fn () => $this->rules()->pass($state, 1), 'only pass after drawing');
        $this->assertConserved($state);
    }

    public function test_uno_is_called_after_the_second_last_card_and_can_be_caught_until_the_next_move(): void
    {
        // Not before: holding two cards, even on your turn with a playable one, there is nothing to call yet.
        $state = $this->table([['red_1_1', 'red_2_1'], ['red_3_1', 'blue_3_1'], ['green_4_1', 'green_5_1']], 'red_9_1');
        $this->assertFalse($this->rules()->canCallUno($state, 0));
        $this->rejects(fn () => $this->rules()->callUno($state, 0), 'right after playing down to one card');

        // Right after the play: the player may call it, and is then safe.
        $this->rules()->play($state, 0, 'red_1_1');
        $this->assertTrue($this->rules()->canCallUno($state, 0));
        $this->assertFalse($this->rules()->canCallUno($state, 1));
        $this->rules()->callUno($state, 0);
        $this->assertNull($state->unoExposed);
        $this->assertTrue($state->players[0]['uno']);
        $this->assertFalse($this->rules()->canCallUno($state, 0), 'Called once is enough.');
        $this->rejects(fn () => $this->rules()->catchUno($state, 1, 0), 'safe');

        // Too slow: any opponent may catch first; two cards penalty and the call is gone.
        $state = $this->table([['red_1_1', 'red_2_1'], ['red_3_1', 'blue_3_1'], ['green_4_1', 'green_5_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_1_1');
        $this->assertSame(0, $state->unoExposed);
        $this->rejects(fn () => $this->rules()->catchUno($state, 0, 0), 'safe');
        $this->rules()->catchUno($state, 2, 0);
        $this->assertCount(3, $state->players[0]['hand']);
        $this->assertNull($state->unoExposed);
        $this->assertFalse($this->rules()->canCallUno($state, 0));

        // Nobody caught it and the next player moved: too late for both.
        $state = $this->table([['red_1_1', 'red_2_1'], ['red_3_1', 'blue_3_1'], ['green_4_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_1_1');
        $this->rules()->play($state, 1, 'red_3_1');
        $this->rejects(fn () => $this->rules()->catchUno($state, 2, 0), 'safe');
        $this->assertFalse($this->rules()->canCallUno($state, 0));
        $this->assertConserved($state);
    }

    public function test_finishing_ranks_the_player_cancels_the_last_card_effect_and_play_continues(): void
    {
        $state = $this->table([['red_draw2_1'], ['red_3_1', 'blue_3_1'], ['green_4_1', 'red_5_1'], ['red_6_1', 'red_7_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'red_draw2_1');
        $this->assertSame([UnoState::DONE, 1], [$state->players[0]['status'], $state->players[0]['rank']]);
        $this->assertCount(2, $state->players[1]['hand'], 'The finishing +2 does nothing.');
        $this->assertSame(1, $state->currentSeat);
        $this->assertSame(UnoState::PLAYING, $state->status);

        // A finishing Reverse does not change direction; finished seats are skipped.
        $state = $this->table([['red_reverse_1'], ['red_3_1', 'blue_3_1'], ['green_4_1', 'red_5_1']], 'red_9_1', current: 0);
        $this->rules()->play($state, 0, 'red_reverse_1');
        $this->assertSame([1, 1], [$state->direction, $state->currentSeat]);
        $this->rules()->play($state, 1, 'red_3_1');
        $this->assertSame(2, $state->currentSeat);
        $this->rules()->play($state, 2, 'red_5_1');
        $this->assertSame(1, $state->currentSeat, 'Seat 0 has left the rotation.');

        // A finishing wild needs no colour and keeps the current one.
        $state = $this->table([['wild_1'], ['red_3_1', 'blue_3_1'], ['green_4_1']], 'red_9_1');
        $this->rules()->play($state, 0, 'wild_1');
        $this->assertSame('red', $state->activeColor);
        $this->assertConserved($state);
    }

    public function test_skip_and_reverse_follow_the_active_players_after_some_finish(): void
    {
        // Four seats, seat 1 already finished. Seat 0 skips: seat 2 is skipped, seat 3 plays.
        $state = $this->table([['red_skip_1', 'red_1_1'], [], ['red_2_1', 'red_4_1'], ['red_3_1', 'red_5_1']], 'red_9_1');
        $state->players[1] = [...$state->players[1], 'status' => UnoState::DONE, 'rank' => 1];
        $this->rules()->play($state, 0, 'red_skip_1');
        $this->assertSame(3, $state->currentSeat);
        // With two active players left, a Reverse acts like a Skip.
        $state = $this->table([['red_reverse_1', 'red_1_1'], [], ['red_2_1', 'red_4_1']], 'red_9_1');
        $state->players[1] = [...$state->players[1], 'status' => UnoState::DONE, 'rank' => 1];
        $this->rules()->play($state, 0, 'red_reverse_1');
        $this->assertSame(0, $state->currentSeat);
    }

    public function test_four_players_rank_in_finishing_order_and_the_last_one_loses(): void
    {
        // A, B, C, D in seats 0–3. C never has a red card and draws blanks.
        $state = $this->table([['red_1_1', 'red_6_1'], ['red_2_1'], ['blue_5_1', 'green_5_1', 'yellow_8_1'], ['red_4_1']], 'red_9_1',
            current: 1, draw: ['green_7_1', 'yellow_7_1']);
        $this->rules()->play($state, 1, 'red_2_1');           // B finishes 1st
        $this->rules()->draw($state, 2);
        $this->rules()->play($state, 3, 'red_4_1');           // D finishes 2nd
        $this->assertSame(0, $state->currentSeat);
        $this->rules()->play($state, 0, 'red_1_1');
        $this->rules()->draw($state, 2);
        $this->rules()->play($state, 0, 'red_6_1');           // A finishes 3rd, C is left holding cards
        $this->assertSame(UnoState::FINISHED, $state->status);
        $this->assertSame([3, 1, 4, 2], array_column($state->players, 'rank'));
        $this->assertSame([UnoState::DONE, UnoState::DONE, UnoState::LOSER, UnoState::DONE], array_column($state->players, 'status'));
        $this->assertConserved($state);
    }

    public function test_hundreds_of_automatic_matches_end_with_unique_ranks_one_loser_and_conserved_cards(): void
    {
        foreach (UnoState::STACKING as $stacking) {
            foreach (range(1, 120) as $seed) {
                $count = 2 + $seed % 5;
                $rules = $this->rules($seed);
                $state = $rules->start(array_map(fn ($i) => 'P'.$i, range(1, $count)), $stacking);
                for ($turn = 0; $state->status === UnoState::PLAYING && $turn < 5000; $turn++) {
                    $rules->autoAct($state);
                }
                $this->assertSame(UnoState::FINISHED, $state->status, "Seed $seed did not finish.");
                $ranks = array_column($state->players, 'rank');
                sort($ranks);
                $this->assertSame(range(1, $count), $ranks);
                $this->assertCount(1, array_filter($state->players, fn ($player) => $player['status'] === UnoState::LOSER));
                $this->assertSame($count, max(array_column(array_filter($state->players, fn ($player) => $player['status'] === UnoState::LOSER), 'rank')));
                $this->assertConserved($state);
            }
        }
    }

    public function test_a_timeout_skips_the_turn_takes_a_waiting_penalty_and_applies_once(): void
    {
        $state = $this->table([['red_1_1', 'blue_2_1'], ['green_3_1'], ['red_4_1']], 'red_9_1');
        $this->rules()->timeout($state, 1);
        $this->assertSame([1, 2], [$state->currentSeat, $state->turnNumber]);
        $this->assertCount(2, $state->players[0]['hand'], 'Skipped, nothing drawn.');
        $this->rules()->timeout($state, 1);
        $this->assertSame(1, $state->currentSeat, 'A stale timeout for an old turn changes nothing.');

        // A penalty waiting for the slow player is taken, not dodged.
        $state = $this->table([['red_draw2_1', 'red_1_1'], ['blue_draw2_1', 'green_5_1'], ['red_3_1']], 'red_9_1', stacking: UnoState::STACK_SAME);
        $this->rules()->play($state, 0, 'red_draw2_1');
        $this->rules()->timeout($state, $state->turnNumber);
        $this->assertCount(4, $state->players[1]['hand']);
        $this->assertSame([UnoState::NONE, 2], [$state->pendingEffect, $state->currentSeat]);

        // A playable drawn card is kept, and the turn moves on.
        $state = $this->table([['blue_2_1'], ['green_3_1']], 'red_9_1', draw: ['red_8_1']);
        $this->rules()->draw($state, 0);
        $this->rules()->timeout($state, $state->turnNumber);
        $this->assertSame([null, 1], [$state->drawnCard, $state->currentSeat]);
        $this->assertContains('red_8_1', $state->players[0]['hand']);
        $this->assertConserved($state);
    }

    public function test_a_forfeit_ranks_last_and_when_one_player_remains_they_win(): void
    {
        $state = $this->table([['red_1_1', 'blue_1_1'], ['red_2_1', 'blue_2_1'], ['red_3_1', 'green_3_1']], 'red_9_1', current: 1);
        $this->rules()->forfeit($state, 1);
        $this->assertSame([UnoState::LOSER, 3], [$state->players[1]['status'], $state->players[1]['rank']]);
        $this->assertSame(2, $state->currentSeat, 'The leaver held the turn, so it moves on.');
        $this->rules()->forfeit($state, 0);
        $this->assertSame(UnoState::FINISHED, $state->status);
        $this->assertSame([UnoState::DONE, 1], [$state->players[2]['status'], $state->players[2]['rank']]);
        $this->assertSame(2, $state->players[0]['rank']);
        $this->assertConserved($state);
    }
}
