<?php

namespace App\Domains;

use App\Models\Card;
use DomainException;
use Illuminate\Support\Collection;

class AnimeDomain implements CardPoolDomain
{
    public function key(): string
    {
        return 'anime';
    }

    public function label(): string
    {
        return 'Anime';
    }

    public function rulesText(): string
    {
        return 'Collect original heroes. Two or more from one series unlock +10% of your total base power, once, rounded down.';
    }

    public function drawPair(array $excludeCardIds): array
    {
        return $this->drawCards($excludeCardIds, 2);
    }

    public function drawCards(array $excludeCardIds, int $count, ?string $position = null): array
    {
        $cards = Card::where('domain', $this->key())->whereNotIn('id', $excludeCardIds)->inRandomOrder()->limit($count)->get();
        if ($cards->count() !== $count) {
            throw new DomainException(__('Not enough unique cards to continue. Seed the card catalog.'));
        }

        return $cards->all();
    }

    public function powerOf(Card $card): int
    {
        return $card->power;
    }

    public function applyTwists(Collection $playerCards): int
    {
        $eligible = $playerCards->groupBy('series')->contains(fn ($cards) => $cards->count() >= 2);

        return $eligible ? (int) floor($playerCards->sum(fn (Card $card) => $this->powerOf($card)) * 0.1) : 0;
    }
}
