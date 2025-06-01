<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Season>
 */
class SeasonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(2020, 2030);

        return [
            'name' => $year . ' - ' . $year + 1,
            'start_year' => $year,
            'end_year' => $year + 1,
        ];
    }
}
