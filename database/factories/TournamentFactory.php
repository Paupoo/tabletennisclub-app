<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tournament>
 */
class TournamentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'start_date' => fake()->dateTimeThisMonth(),
            'start_date' => fake()->dateTimeThisMonth(),
            'max_users' => fake()->randomNumber(2),
            'price' => fake()->randomFloat(2,10,30)
        ];
    }
}
