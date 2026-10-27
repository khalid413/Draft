<?php

namespace App\Uno\Classic;

use App\Uno\UnoCard;
use App\Uno\UnoRules;
use App\Uno\UnoVariant;

/**
 * UNO Classic: 108 cards. Per colour one 0, two each of 1–9, Skip, Reverse and Draw Two; plus four Wild and four Wild Draw Four.
 * The 72 supplied faces cover them: both copies of a number share one picture, each action and wild copy has its own file.
 */
final class ClassicVariant implements UnoVariant
{
    private const ART = '/cards/uno/classic/';

    public function key(): string
    {
        return 'classic';
    }

    public function deck(): array
    {
        $ids = [];
        foreach (UnoCard::COLORS as $color) {
            $ids[] = UnoCard::make($color, '0', 1)->id();
            foreach ([...range(1, 9), ...UnoCard::ACTIONS] as $value) {
                foreach ([1, 2] as $copy) {
                    $ids[] = UnoCard::make($color, (string) $value, $copy)->id();
                }
            }
        }
        foreach (UnoCard::WILDS as $wild) {
            foreach ([1, 2, 3, 4] as $copy) {
                $ids[] = UnoCard::make(null, $wild, $copy)->id();
            }
        }

        return $ids;
    }

    public function handSize(): int
    {
        return 7;
    }

    public function image(UnoCard $card): string
    {
        return self::ART.($card->isNumber() ? $card->face() : $card->id()).'.png';
    }

    public function backImage(): string
    {
        return self::ART.'back.webp';
    }

    public function rules(): UnoRules
    {
        return app(ClassicRules::class);
    }
}
