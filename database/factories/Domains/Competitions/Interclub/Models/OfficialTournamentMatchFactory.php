<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\OfficialTournamentMatch;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficialTournamentMatch>
 */
class OfficialTournamentMatchFactory extends Factory
{
    protected $model = OfficialTournamentMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ourSets = fake()->numberBetween(0, 3);
        $theirSets = $ourSets === 3 ? fake()->numberBetween(0, 2) : 3;

        return [
            'opponent_club' => fake()->city(),
            'opponent_licence' => (string) fake()->numberBetween(100000, 199999),
            'opponent_name' => fake()->name(),
            'opponent_ranking' => fake()->randomElement(['B4', 'C2', 'C4', 'D0', 'E0', 'NC']),
            'our_sets' => $ourSets,
            'played_on' => fake()->dateTimeBetween('-2 months'),
            'player_licence' => (string) fake()->numberBetween(100000, 199999),
            'player_name' => fake()->name(),
            'player_ranking' => fake()->randomElement(['C4', 'D2', 'E0', 'NC']),
            'season_id' => Season::factory(),
            'serie_name' => fake()->randomElement(['Série C', 'Série D', 'Série E', 'Série NC']),
            'their_sets' => $theirSets,
            'tournament_name' => 'Critérium B - ' . fake()->city(),
            'user_id' => User::factory(),
            'we_won' => $ourSets > $theirSets,
        ];
    }

    /**
     * Filed against this member, under the licence they actually hold.
     */
    public function playedBy(User $user): self
    {
        return $this->state(fn (): array => [
            'player_licence' => $user->licence ?? (string) fake()->numberBetween(100000, 199999),
            'player_name' => trim($user->first_name . ' ' . $user->last_name),
            'user_id' => $user->id,
        ]);
    }
}
