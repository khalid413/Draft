<?php

namespace Database\Factories;

use App\Enums\GamePhase;
use App\Game\GameState;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GameFactory extends Factory
{
    public function definition(): array
    {
        $state = new GameState(
            players: [['name' => 'Alex', 'cards' => []], ['name' => 'Sam', 'cards' => []]],
            phase: GamePhase::PassDevice,
            seed: bin2hex(random_bytes(32)),
        );

        return ['uuid' => (string) Str::uuid(), 'domain' => 'custom', 'state' => $state->jsonSerialize(), 'status' => 'pass_device'];
    }
}
