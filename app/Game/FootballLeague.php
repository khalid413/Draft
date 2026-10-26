<?php

namespace App\Game;

/** One match per pair; tied leaders share the title after points/GD/goals scored. */
final class FootballLeague
{
    public function simulate(array $players, string $seed): array
    {
        $table = array_map(fn ($index) => ['playerIndex' => $index, 'played' => 0, 'won' => 0, 'drawn' => 0,
            'lost' => 0, 'gf' => 0, 'ga' => 0, 'gd' => 0, 'points' => 0], array_keys($players));
        $matches = [];
        for ($a = 0; $a < count($players); $a++) {
            for ($b = $a + 1; $b < count($players); $b++) {
                $match = (new FootballMatchSimulator)->simulate([$players[$a], $players[$b]], $seed.':'.$a.':'.$b);
                $match['playerIndices'] = [$a, $b];
                $matches[] = $match;
                foreach ([[$a, 0, 1], [$b, 1, 0]] as [$index, $own, $other]) {
                    $row = &$table[$index];
                    $gf = $match['goals'][$own];
                    $ga = $match['goals'][$other];
                    $row['played']++;
                    $row['gf'] += $gf;
                    $row['ga'] += $ga;
                    $row['gd'] += $gf - $ga;
                    $row[$gf === $ga ? 'drawn' : ($gf > $ga ? 'won' : 'lost')]++;
                    $row['points'] += $gf === $ga ? 1 : ($gf > $ga ? 3 : 0);
                    unset($row);
                }
            }
        }
        usort($table, fn ($a, $b) => [$b['points'], $b['gd'], $b['gf']] <=> [$a['points'], $a['gd'], $a['gf']]);
        $best = $table[0];
        $leaders = array_values(array_filter($table, fn ($row) => [$row['points'], $row['gd'], $row['gf']] === [$best['points'], $best['gd'], $best['gf']]));

        return ['matches' => $matches, 'table' => $table, 'winners' => array_column($leaders, 'playerIndex')];
    }
}
