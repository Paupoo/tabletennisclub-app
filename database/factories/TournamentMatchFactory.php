<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TournamentMatch>
 */
class TournamentMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pool_id' => 1,
            'table_id' => 1,
            'player1_id' => User::inRandomOrder()->first(),
            'player2_id' => User::inRandomOrder()->first(),
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'winner_id' => null,
            'status' => 'scheduled', // 'scheduled', 'in_progress', 'completed'
            'match_order' => 1,
            'scheduled_time' => null,
            'tournament_id' => 1,
            'round' => null,
            'next_match_id' => null,
            'bronze_match_id' => null,
            'is_bronze_match' => false,
        ];
    }
}
