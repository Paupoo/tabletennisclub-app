<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Interclub\Models;

use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\LeagueCategory;
use App\Domains\Shared\Enums\LeagueLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<League>
 */
class LeagueFactory extends Factory
{
    protected $model = League::class;

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
            'level' => fake()->randomElement(array_column(LeagueLevel::cases(), 'name')),
            'category' => fake()->randomElement(array_column(LeagueCategory::cases(), 'name')),
            'season_id' => Season::factory(),
        ];
    }
}
