<?php

namespace App\Uno;

/**
 * One flavour of UNO: its physical deck, its artwork and its rules engine. Rooms, seats, storage,
 * views and sync are shared, so a new variant only implements this and registers in UnoVariants.
 */
interface UnoVariant
{
    public function key(): string;

    /** @return list<string> one id per physical card */
    public function deck(): array;

    public function handSize(): int;

    public function image(UnoCard $card): string;

    public function backImage(): string;

    public function rules(): UnoRules;
}
