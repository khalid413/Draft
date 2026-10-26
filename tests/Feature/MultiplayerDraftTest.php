<?php

namespace Tests\Feature;

use App\Domains\FootballDomain;
use App\Game\DraftSchedule;
use App\Game\FootballLeague;
use App\Game\GameEngine;
use App\Game\GameState;
use App\Game\PublicGameView;
use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiplayerDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_three_and_four_players_receive_equal_unique_rosters_and_complete_formations(): void
    {
        Card::factory()->count(28)->create(['domain' => 'custom']);
        foreach (FootballDomain::REQUIREMENTS as $position => $count) {
            Card::factory()->count($count * 2)->create(['domain' => 'football', 'position' => $position, 'power' => 80]);
        }
        foreach ([3, 4] as $count) {
            foreach (['custom', 'football'] as $domain) {
                $engine = app(GameEngine::class);
                $state = $engine->startGame(array_slice(['Alex', 'Sam', 'Kim', 'Jo'], 0, $count), $domain, 7);
                $size = $domain === 'football' ? 11 : 7;
                for ($i = 0; $i < $state->roundLimit; $i++) {
                    $engine->drawRound($state);
                    $private = $state->rounds[$i];
                    $view = (new PublicGameView)->transform($state, $i);
                    $this->assertNull($view['extraCard']);
                    foreach (array_slice($private['cards'], 1) as $hidden) {
                        $this->assertStringNotContainsString($hidden['name'], json_encode($view));
                    }
                    $engine->pick($state, $state->startingPlayerIndex, $private['tokens'][$i % 2]);
                    $engine->revealRound($state);
                    if ($count === 3) {
                        $this->assertSame($private['cards'][2], (new PublicGameView)->transform($state, $i)['extraCard']);
                    }
                    // Persist/resume mid-draft without losing pairings or slot assignment.
                    $state = GameState::fromArray(json_decode(json_encode($state), true));
                    if ($i + 1 < $state->roundLimit) {
                        $engine->nextRound($state);
                    }
                }
                $engine->finish($state);
                $ids = [];
                foreach ($state->players as $player) {
                    $this->assertCount($size, $player['cards']);
                    $ids = array_merge($ids, array_column($player['cards'], 'id'));
                    if ($domain === 'football') {
                        $this->assertSame(array_column((new FootballDomain)->slots(), 'key'), array_column($player['cards'], 'slot'));
                    }
                }
                $this->assertCount($size * $count, array_unique($ids));
                if ($domain === 'football') {
                    $league = $state->results['league'];
                    $this->assertCount($count * ($count - 1) / 2, $league['matches']);
                    $this->assertSame($league, (new FootballLeague)->simulate($state->players, $state->seed));
                    foreach ($league['table'] as $row) {
                        $this->assertSame($count - 1, $row['played']);
                        $this->assertSame($row['won'] * 3 + $row['drawn'], $row['points']);
                        $this->assertSame($row['gf'] - $row['ga'], $row['gd']);
                        $gf = $ga = 0;
                        foreach ($league['matches'] as $match) {
                            $this->assertSame(15000, $match['durationMs']);
                            $side = array_search($row['playerIndex'], $match['playerIndices'], true);
                            if ($side !== false) {
                                $gf += $match['goals'][$side];
                                $ga += $match['goals'][1 - $side];
                            }
                        }
                        $this->assertSame([$gf, $ga], [$row['gf'], $row['ga']]);
                    }
                }
            }
        }
    }

    public function test_picker_counts_are_balanced_and_four_player_pairs_rotate(): void
    {
        foreach ([2, 3, 4] as $players) {
            foreach ([3, 5, 7, 11] as $size) {
                $counts = array_fill(0, $players, 0);
                foreach (DraftSchedule::build($players, $size) as $turn) {
                    $counts[$turn['activePlayer']]++;
                }
                $this->assertLessThanOrEqual(1, max($counts) - min($counts));
            }
        }
        $pairs = array_map(function ($turn) {
            $pair = [$turn['activePlayer'], $turn['recipientPlayer']];
            sort($pair);

            return implode('-', $pair);
        }, DraftSchedule::build(4, 3));
        $this->assertCount(6, array_unique($pairs));
    }

    public function test_http_accepts_four_players_and_validates_names_and_collection_capacity(): void
    {
        Card::factory()->count(12)->create(['domain' => 'custom', 'series' => 'DC Heroes']);
        $settings = ['players' => ['Alex', 'Sam', 'Kim', 'Jo'], 'domain' => 'custom', 'catalog' => 'DC Heroes', 'rounds' => 3, 'timer' => false];
        $this->postJson('/game/start', array_replace($settings, ['players' => ['Alex', 'Sam', 'alex']]))->assertUnprocessable();
        $this->postJson('/game/start', array_replace($settings, ['players' => ['Alex', 'Sam', 'Kim', 'Jo', 'Lee']]))->assertUnprocessable();
        $this->postJson('/game/start', array_replace($settings, ['rounds' => 5]))->assertUnprocessable();
        $this->postJson('/game/start', $settings)->assertOk()->assertJsonCount(4, 'players')->assertJsonPath('roundLimit', 6)->assertJsonPath('cardsPerPlayer', 3);
    }
}
