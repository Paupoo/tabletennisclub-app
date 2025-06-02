<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeagueCategory;
use App\Enums\LeagueLevel;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\League>
 */
class LeagueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $thisYear = now()->format('Y');

        return [
            'division' => fake()->numberBetween(1, 5) . fake()->randomLetter(),
            'level' => fake()->randomElement(array_column(LeagueLevel::cases(), 'value')),
            'category' => fake()->randomElement(array_column(LeagueCategory::cases(), 'value')),
            'season_id' => fake()->randemElement(Season::select('id')->get()),
        ];
    }
}
