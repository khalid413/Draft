<?php

namespace App\Uno;

use InvalidArgumentException;

/**
 * One physical UNO card. Duplicate designs are separate cards with their own copy number,
 * so "red_7_1" and "red_7_2" are two cards that share one picture.
 */
final class UnoCard
{
    public const COLORS = ['red', 'blue', 'green', 'yellow'];

    public const ACTIONS = ['skip', 'reverse', 'draw2'];

    public const WILDS = ['wild', 'wild4'];

    private function __construct(public readonly ?string $color, public readonly string $value, public readonly int $copy) {}

    public static function fromId(string $id): self
    {
        if (preg_match('/\A(red|blue|green|yellow)_([0-9]|skip|reverse|draw2)_([1-9])\z/', $id, $match)) {
            return new self($match[1], $match[2], (int) $match[3]);
        }
        if (preg_match('/\A(wild4?)_([1-9])\z/', $id, $match)) {
            return new self(null, $match[1], (int) $match[2]);
        }
        throw new InvalidArgumentException('Unknown card.');
    }

    public static function make(?string $color, string $value, int $copy): self
    {
        return self::fromId(($color ? $color.'_' : '').$value.'_'.$copy);
    }

    public function id(): string
    {
        return ($this->color ? $this->color.'_' : '').$this->value.'_'.$this->copy;
    }

    /** The design, without the copy number: two cards with the same face match each other. */
    public function face(): string
    {
        return ($this->color ? $this->color.'_' : '').$this->value;
    }

    public function isWild(): bool
    {
        return in_array($this->value, self::WILDS, true);
    }

    public function isNumber(): bool
    {
        return ctype_digit($this->value);
    }

    public static function colorIsValid(?string $color): bool
    {
        return in_array($color, self::COLORS, true);
    }
}
