<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Tournament;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentPair>
 */
class TournamentPairFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tournament_id' => Tournament::factory(),
            'player1_id' => User::factory(),
            'player2_id' => User::factory(),
            'registered_by' => User::factory(),
        ];
    }
}
