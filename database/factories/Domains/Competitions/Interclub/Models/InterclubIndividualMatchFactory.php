<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\InterclubIndividualMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterclubIndividualMatch>
 */
class InterclubIndividualMatchFactory extends Factory
{
    protected $model = InterclubIndividualMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ourSets = fake()->numberBetween(0, 3);
        $theirSets = $ourSets === 3 ? fake()->numberBetween(0, 2) : 3;

        return [
            'interclub_id' => Interclub::factory(),
            'is_double' => false,
            'is_forfeit' => false,
            'opponent_licence' => (string) fake()->numberBetween(100000, 199999),
            'opponent_name' => fake()->name(),
            'opponent_ranking' => fake()->randomElement(['B4', 'C2', 'C4', 'D0', 'E0', 'NC']),
            'our_sets' => $ourSets,
            'position' => fake()->numberBetween(1, 16),
            'their_sets' => $theirSets,
            'user_id' => User::factory(),
            'we_won' => $ourSets > $theirSets,
        ];
    }

    /**
     * The line the federation never names: a set score and nobody on it.
     */
    public function double(): self
    {
        return $this->state(fn (): array => [
            'is_double' => true,
            'opponent_licence' => null,
            'opponent_name' => null,
            'opponent_ranking' => null,
            'position' => 7,
            'user_id' => null,
        ]);
    }

    /**
     * Both players named, no sets played.
     */
    public function forfeited(): self
    {
        return $this->state(fn (): array => [
            'is_forfeit' => true,
            'our_sets' => null,
            'their_sets' => null,
        ]);
    }

    /**
     * One of ours whose licence the club roster does not know.
     */
    public function unmatchedPlayer(): self
    {
        return $this->state(fn (): array => [
            'our_player_licence' => (string) fake()->numberBetween(100000, 199999),
            'our_player_name' => fake()->name(),
            'user_id' => null,
        ]);
    }
}
