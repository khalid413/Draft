<?php

namespace App\Domains;

use App\Models\Card;
use Illuminate\Support\Collection;

interface DomainContract
{
    public function key(): string;

    public function label(): string;

    public function rulesText(): string;

    /** @return array{0: Card, 1: Card} */
    public function drawPair(array $excludeCardIds): array;

    public function powerOf(Card $card): int;

    /** One non-stacking bonus, rounded down. */
    public function applyTwists(Collection $playerCards): int;
}
