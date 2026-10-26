<?php

namespace Tests\Unit;

use App\Game\FootballMatchSimulator;
use PHPUnit\Framework\TestCase;

class FootballMatchSimulatorTest extends TestCase
{
    private function team(int $rating): array
    {
        return ['cards' => array_map(fn ($position) => ['position' => $position, 'power' => $rating],
            ['GK', 'LB', 'CB', 'CB', 'RB', 'CM', 'CM', 'CM', 'LW', 'ST', 'RW'])];
    }

    public function test_match_is_repeatable_and_events_agree_with_score_and_winner(): void
    {
        $simulator = new FootballMatchSimulator;
        $teams = [$this->team(90), $this->team(75)];
        $match = $simulator->simulate($teams, 'stable-seed');
        $this->assertSame($match, $simulator->simulate($teams, 'stable-seed'));
        $goals = [0, 0];
        $minute = 0;
        foreach ($match['events'] as $event) {
            $this->assertGreaterThanOrEqual($minute, $event['minute']);
            $this->assertLessThanOrEqual(90, $event['minute']);
            $minute = $event['minute'];
            if ($event['type'] === 'goal') {
                $goals[$event['team']]++;
            }
        }
        $this->assertSame($goals, $match['goals']);
        $this->assertSame($goals[0] === $goals[1] ? [0, 1] : [$goals[0] > $goals[1] ? 0 : 1], $match['winners']);
    }

    public function test_stronger_team_has_advantage_but_draws_and_upsets_are_possible(): void
    {
        $simulator = new FootballMatchSimulator;
        $goals = [0, 0];
        $draw = false;
        $upset = false;
        for ($seed = 0; $seed < 200; $seed++) {
            $match = $simulator->simulate([$this->team(90), $this->team(80)], (string) $seed);
            $goals[0] += $match['goals'][0];
            $goals[1] += $match['goals'][1];
            $draw = $draw || count($match['winners']) === 2;
            $upset = $upset || $match['winners'] === [1];
        }
        $this->assertGreaterThan($goals[1], $goals[0]);
        $this->assertTrue($draw);
        $this->assertTrue($upset);
    }

    public function test_lineup_positions_affect_strength_even_with_identical_total_ovr(): void
    {
        $team = $this->team(80);
        $team['cards'][0]['power'] = 100;
        $team['cards'][9]['power'] = 60;
        $other = $team;
        $other['cards'][0]['power'] = 60;
        $other['cards'][9]['power'] = 100;
        $match = (new FootballMatchSimulator)->simulate([$team, $other], 'positions');
        $this->assertNotSame($match['strengths'][0], $match['strengths'][1]);
        $this->assertSame(100.0, $match['strengths'][0]['keeper']);
        $this->assertSame(60.0, $match['strengths'][1]['keeper']);
    }
}
