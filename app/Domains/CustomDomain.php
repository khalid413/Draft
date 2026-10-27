<?php

namespace App\Domains;

use App\Models\Card;
use DomainException;
use Illuminate\Support\Collection;

class CustomDomain implements CardPoolDomain
{
    public function __construct(private ?string $catalog = null) {}

    public function forCatalog(?string $catalog): self
    {
        return new self($catalog);
    }

    public function key(): string
    {
        return 'custom';
    }

    public function label(): string
    {
        return 'My cards';
    }

    public function rulesText(): string
    {
        return 'Play with your own pictures, names and power. Highest total wins.';
    }

    public function drawPair(array $excludeCardIds): array
    {
        return $this->drawCards($excludeCardIds, 2);
    }

    public function drawCards(array $excludeCardIds, int $count, ?string $position = null): array
    {
        $cards = Card::where('domain', $this->key())
            ->when($this->catalog !== null, fn ($query) => $query->where('series', $this->catalog))
            ->whereNotIn('id', $excludeCardIds)->inRandomOrder()->limit($count)->get();
        if ($cards->count() !== $count) {
            throw new DomainException(__('Add more cards to your library before starting this game.'));
        }

        return $cards->all();
    }

    public function powerOf(Card $card): int
    {
        return $card->power;
    }

    public function applyTwists(Collection $playerCards): int
    {
        return 0;
    }
}
