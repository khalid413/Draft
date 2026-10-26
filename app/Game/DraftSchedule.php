<?php

namespace App\Game;

final class DraftSchedule
{
    public static function build(int $players, int $cardsPerPlayer): array
    {
        $turns = [];
        $offset = 0;
        for ($slot = 0; $slot < $cardsPerPlayer; $slot++) {
            if ($players === 4) {
                $pairs = [[[0, 1], [2, 3]], [[2, 0], [3, 1]], [[0, 3], [1, 2]]][$slot % 3];
                if (intdiv($slot, 3) % 2 === 1) {
                    $pairs = array_map('array_reverse', $pairs);
                }
            } else {
                $pairs = [[$slot % $players, ($slot + 1) % $players]];
            }
            foreach ($pairs as [$active, $recipient]) {
                $count = $players === 3 ? 3 : 2;
                $turns[] = ['activePlayer' => $active, 'recipientPlayer' => $recipient,
                    'extraPlayer' => $players === 3 ? ($slot + 2) % 3 : null,
                    'slotIndex' => $slot, 'offset' => $offset, 'count' => $count];
                $offset += $count;
            }
        }

        return $turns;
    }
}
