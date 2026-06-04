<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Interclub\Models;

use App\Domains\Shared\Enums\InterclubResult;
use App\Domains\Competitions\Interclub\Models\MatchResult;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchResult>
 */
class MatchResultFactory extends Factory
{
    protected $model = MatchResult::class;

    public function bye(): static
    {
        return $this->state(['is_bye' => true, 'match_date' => null, 'opponent_name' => null, 'score' => null, 'result' => null]);
    }

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-6 months', 'now');

        return [
            'team_id' => Team::factory(),
            'season_id' => Season::factory(),
            'match_date' => $date->format('Y-m-d'),
            'week_number' => (int) $date->format('W'),
            'is_home' => fake()->boolean(),
            'opponent_name' => fake()->company() . ' ' . strtoupper(fake()->randomLetter()),
            'score' => fake()->numberBetween(0, 16) . '-' . fake()->numberBetween(0, 16),
            'result' => fake()->randomElement(InterclubResult::cases()),
            'is_bye' => false,
        ];
    }
}
