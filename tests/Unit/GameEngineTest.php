<?php

namespace Tests\Unit;

use App\Domains\AnimeDomain;
use App\Domains\DomainRegistry;
use App\Enums\GamePhase;
use App\Game\GameEngine;
use App\Game\GameState;
use App\Game\PublicGameView;
use App\Models\Card;
use DomainException;
use Tests\TestCase;

class GameEngineTest extends TestCase
{
    private function engine(array $powers = [40, 60, 50, 70, 60, 80], bool $sameSeries = false, ?\Closure $clock = null): GameEngine
    {
        $cards = [];
        foreach ($powers as $i => $power) {
            $cards[] = new Card(['id' => $i + 1, 'name' => 'Hero '.($i + 1), 'series' => $sameSeries ? 'Moon' : 'Series '.$i,
                'image_path' => '/test.svg', 'power' => $power]);
        }
        $domain = new class($cards) extends AnimeDomain
        {
            public function __construct(private array $cards) {}

            public function drawCards(array $excludeCardIds, int $count, ?string $position = null): array
            {
                return array_slice(array_values(array_filter($this->cards, fn ($card) => ! in_array($card->id, $excludeCardIds))), 0, $count);
            }
        };
        $registry = new DomainRegistry;
        $registry->register($domain);

        return new GameEngine($registry, $clock);
    }

    private function play(GameEngine $engine, GameState $state, int $slot = 0): void
    {
        $engine->drawRound($state);
        $engine->pick($state, $state->startingPlayerIndex, $state->rounds[$state->currentRound]['tokens'][$slot]);
        $engine->revealRound($state);
    }

    public function test_pick_reveal_and_alternating_starters(): void
    {
        $engine = $this->engine();
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        $this->assertSame(GamePhase::PassDevice, $state->phase);
        $this->play($engine, $state, 1);
        $this->assertSame(60, $state->players[0]['cards'][0]['power']);
        $this->assertSame(40, $state->players[1]['cards'][0]['power']);
        $engine->nextRound($state);
        $this->assertSame(1, $state->startingPlayerIndex);
        $this->play($engine, $state);
        $engine->nextRound($state);
        $this->assertSame(0, $state->startingPlayerIndex);
        $this->play($engine, $state);
        $engine->finish($state);
        $this->assertSame([190, 170], array_column($state->results['scores'], 'total'));
        $this->assertSame([0], $state->results['winners']);
        $ids = array_merge(...array_map(fn ($r) => array_column($r['cards'], 'id'), $state->rounds));
        $this->assertCount(6, array_unique($ids));
        $this->assertEquals($state, GameState::fromArray(json_decode(json_encode($state), true)));
    }

    public function test_series_bonus_is_once_rounded_down_and_draw_is_supported(): void
    {
        $engine = $this->engine([51, 51, 51, 51, 51, 51], true);
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        for ($i = 0; $i < 3; $i++) {
            $this->play($engine, $state);
            if ($i < 2) {
                $engine->nextRound($state);
            }
        }
        $engine->finish($state);
        $this->assertSame([0, 1], $state->results['winners']);
        $this->assertSame(['base' => 153, 'bonus' => 15, 'total' => 168], $state->results['scores'][0]);
    }

    public function test_public_view_does_not_leak_hidden_identity_power_seed_or_picks(): void
    {
        $engine = $this->engine();
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        $view = new PublicGameView;
        $this->assertSame([], $view->transform($state, 0)['cards']);
        $engine->drawRound($state);
        $hidden = $state->rounds[0]['cards'][1];
        $public = $view->transform($state, 1);
        $this->assertSame(['selectionId', 'hidden'], array_keys($public['cards'][1]));
        $this->assertStringNotContainsString($hidden['name'], json_encode($public));
        $this->assertArrayNotHasKey('seed', $public);
        $this->assertArrayNotHasKey('picks', $public);
        $engine->pick($state, 0, $state->rounds[0]['tokens'][1]);
        $this->assertTrue($view->transform($state, 2)['cards'][1]['hidden']);
        $engine->revealRound($state);
        $this->assertSame($hidden['name'], $view->transform($state, 3)['cards'][1]['name']);
    }

    public function test_wrong_player_cannot_pick(): void
    {
        $engine = $this->engine();
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        $engine->drawRound($state);
        $this->expectException(DomainException::class);
        $engine->pick($state, 1, $state->rounds[0]['tokens'][0]);
    }

    public function test_double_pick_is_rejected(): void
    {
        $engine = $this->engine();
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        $engine->drawRound($state);
        $engine->pick($state, 0, $state->rounds[0]['tokens'][0]);
        $this->expectException(DomainException::class);
        $engine->pick($state, 0, $state->rounds[0]['tokens'][1]);
    }

    public function test_no_phase_skipping(): void
    {
        $engine = $this->engine();
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        $this->expectException(DomainException::class);
        $engine->finish($state);
    }

    public function test_cannot_reveal_before_a_pick(): void
    {
        $engine = $this->engine();
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3);
        $engine->drawRound($state);
        $this->expectException(DomainException::class);
        $engine->revealRound($state);
    }

    public function test_timer_is_server_enforced_and_resumable(): void
    {
        $now = 100;
        $engine = $this->engine(clock: function () use (&$now) {
            return $now;
        });
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3, true);
        $engine->drawRound($state);
        $this->assertSame(130, $state->pickDeadline);
        $state = GameState::fromArray($state->jsonSerialize());
        $now = 131;
        $engine->timeout($state);
        $engine->revealRound($state);
        $this->assertCount(1, $state->players[0]['cards']);
        $this->assertNull($state->pickDeadline);
    }

    public function test_early_timeout_is_rejected(): void
    {
        $engine = $this->engine(clock: fn () => 100);
        $state = $engine->startGame(['Alex', 'Sam'], 'anime', 3, true);
        $engine->drawRound($state);
        $this->expectException(DomainException::class);
        $engine->timeout($state);
    }

    public function test_model_supports_more_than_two_players_and_rotates_recipients(): void
    {
        $engine = $this->engine(array_fill(0, 9, 50));
        $state = $engine->startGame(['Alex', 'Sam', 'Kim'], 'anime', 3);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame($i, $state->startingPlayerIndex);
            $this->play($engine, $state);
            if ($i < 2) {
                $engine->nextRound($state);
            }
        }
        $engine->finish($state);
        foreach ($state->players as $player) {
            $this->assertCount(3, $player['cards']);
        }
    }
}
