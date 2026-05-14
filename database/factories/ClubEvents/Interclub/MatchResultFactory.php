<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Interclub;

use App\Enums\InterclubResult;
use App\Models\ClubEvents\Interclub\MatchResult;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Interclub\Team;
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
