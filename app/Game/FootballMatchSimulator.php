<?php

namespace App\Game;

use DomainException;

/** Deterministic, transport-free match using the frozen natural-position lineup. */
final class FootballMatchSimulator
{
    public function simulate(array $players, string $seed): array
    {
        if (count($players) !== 2) {
            throw new DomainException(__('A football match needs two teams.'));
        }
        $strengths = array_map(function ($player) {
            $average = function (array $positions) use ($player): float {
                $cards = array_values(array_filter($player['cards'], fn ($card) => in_array($card['position'], $positions, true)));
                if (! $cards) {
                    throw new DomainException(__('The lineup is incomplete.'));
                }

                return array_sum(array_column($cards, 'power')) / count($cards);
            };

            return ['attack' => $average(['LW', 'ST', 'RW']), 'midfield' => $average(['CM']),
                'defence' => $average(['LB', 'CB', 'RB']), 'keeper' => $average(['GK'])];
        }, $players);
        $goals = [0, 0];
        $events = [];
        foreach ([0, 1] as $team) {
            $own = $strengths[$team];
            $opponent = $strengths[1 - $team];
            // Midfield controls chance quality; attack faces defence and goalkeeper.
            $edge = .55 * $own['attack'] + .45 * $own['midfield']
                - .45 * $opponent['defence'] - .30 * $opponent['keeper'] - .25 * $opponent['midfield'];
            $probability = max(.025, min(.65, .17 * exp($edge / 22)));
            for ($chance = 0; $chance < 9; $chance++) {
                $hash = hash('sha256', $seed.':match-v1:'.$team.':'.$chance);
                $roll = hexdec(substr($hash, 0, 8)) / 4294967296;
                $minute = 5 + $chance * 10 + (hexdec(substr($hash, 8, 2)) % 5);
                $goal = $roll < $probability;
                if ($goal) {
                    $goals[$team]++;
                }
                $events[] = ['minute' => $minute, 'team' => $team, 'type' => $goal ? 'goal' : 'save'];
            }
        }
        usort($events, fn ($a, $b) => [$a['minute'], $a['team']] <=> [$b['minute'], $b['team']]);

        return ['version' => 1, 'durationMs' => 15000, 'goals' => $goals, 'events' => $events,
            'strengths' => $strengths, 'winners' => $goals[0] === $goals[1] ? [0, 1] : [$goals[0] > $goals[1] ? 0 : 1]];
    }
}
