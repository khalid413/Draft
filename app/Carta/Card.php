<?php

namespace App\Carta;

use InvalidArgumentException;

/** One card of the 40-card Spanish/Moroccan deck. Its id ("swords_7") is its identity everywhere. */
final class Card
{
    public const SUITS = ['coins', 'swords', 'cups', 'clubs'];

    public const RANKS = [1, 2, 3, 4, 5, 6, 7, 10, 11, 12];

    public const SUIT_NAMES = [
        'coins' => ['en' => 'Coins', 'ar' => 'ذهب'],
        'swords' => ['en' => 'Swords', 'ar' => 'سيوف'],
        'cups' => ['en' => 'Cups', 'ar' => 'طباق'],
        'clubs' => ['en' => 'Clubs', 'ar' => 'زراوط'],
    ];

    private function __construct(public readonly string $suit, public readonly int $rank) {}

    public static function fromId(string $id): self
    {
        if (! preg_match('/\A(coins|swords|cups|clubs)_(1|2|3|4|5|6|7|10|11|12)\z/', $id, $match)) {
            throw new InvalidArgumentException('Unknown card.');
        }

        return new self($match[1], (int) $match[2]);
    }

    public static function make(string $suit, int $rank): self
    {
        return self::fromId($suit.'_'.$rank);
    }

    /** All 40 cards, generated from the suits and ranks; 8 and 9 do not exist in this deck. */
    public static function deck(): array
    {
        $cards = [];
        foreach (self::SUITS as $suit) {
            foreach (self::RANKS as $rank) {
                $cards[] = new self($suit, $rank);
            }
        }

        return $cards;
    }

    public function id(): string
    {
        return $this->suit.'_'.$this->rank;
    }

    /** skip (1), draw_two (2), change_suit (7) or null for the plain cards 3–6 and 10–12. */
    public function effect(): ?string
    {
        return match ($this->rank) {
            1 => 'skip',
            2 => 'draw_two',
            7 => 'change_suit',
            default => null,
        };
    }

    public function image(): string
    {
        return '/cards/'.$this->suit.'/'.$this->id().'.webp';
    }

    public function toArray(): array
    {
        return ['id' => $this->id(), 'suit' => $this->suit, 'rank' => $this->rank, 'image' => $this->image(), 'effect' => $this->effect()];
    }

    public static function suitIsValid(?string $suit): bool
    {
        return in_array($suit, self::SUITS, true);
    }

    public static function backImage(): string
    {
        return '/cards/back.webp';
    }
}
