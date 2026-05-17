<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Interclub;

use App\Models\ClubEvents\Interclub\Season;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
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
        $year = fake()->unique()->numberBetween(2024, 2034);

        return [
            'name' => $year . '-' . ($year + 1),
            'start_at' => Carbon::parse('first day of September ' . $year),
            'end_at' => Carbon::parse('last day of June ' . $year + 1),
            'is_active' => false,
        ];
    }
}
