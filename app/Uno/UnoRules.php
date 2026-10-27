<?php

namespace App\Uno;

/** The moves a variant's engine accepts. Every method validates the move and mutates the state; nothing trusts the client. */
interface UnoRules
{
    public function start(array $names, bool $stacking, ?int $startingSeat = null): UnoState;

    /** @return list<string> card ids the seat may legally play right now */
    public function legalCards(UnoState $state, int $seat): array;

    public function play(UnoState $state, int $seat, string $cardId, ?string $color = null): UnoState;

    public function draw(UnoState $state, int $seat): UnoState;

    public function pass(UnoState $state, int $seat): UnoState;

    public function accept(UnoState $state, int $seat): UnoState;

    public function challenge(UnoState $state, int $seat): UnoState;

    public function callUno(UnoState $state, int $seat): UnoState;

    public function canCallUno(UnoState $state, int $seat): bool;

    public function catchUno(UnoState $state, int $catcher, int $target): UnoState;

    public function forfeit(UnoState $state, int $seat): UnoState;
}
