<?php

namespace App\Game;

use App\Enums\GamePhase;
use JsonSerializable;

final class GameState implements JsonSerializable
{
    public function __construct(
        public array $players = [],
        public string $domainKey = 'custom',
        public array $rounds = [],
        public int $currentRound = 0,
        public GamePhase $phase = GamePhase::Setup,
        public int $startingPlayerIndex = 0,
        public array $picks = [],
        public string $seed = '',
        public int $roundLimit = 5,
        public bool $timerEnabled = false,
        public ?int $pickDeadline = null,
        public array $results = [],
        public array $deck = [],
        public array $formation = [],
        public ?string $catalog = null,
        public array $schedule = [],
        public ?int $cardsPerPlayer = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $data['phase'] = GamePhase::from($data['phase']);

        return new self(...$data);
    }

    public function jsonSerialize(): array
    {
        return array_replace(get_object_vars($this), ['phase' => $this->phase->value]);
    }
}
