<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CardFactory extends Factory
{
    public function definition(): array
    {
        return ['domain' => 'custom', 'name' => fake()->unique()->name(), 'series' => 'Moon Circuit',
            'image_path' => '/test-card.png', 'power' => fake()->numberBetween(1, 100), 'meta' => []];
    }
}
