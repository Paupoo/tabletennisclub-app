<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Tournament\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentPair>
 */
class TournamentPairFactory extends Factory
{
    protected $model = TournamentPair::class;

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
