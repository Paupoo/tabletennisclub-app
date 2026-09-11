<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Interclub\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Letters are handed out in turn rather than drawn.
     *
     * A team's letter identifies it within its category and season, and the model
     * refuses a second « A » in men. A random draw over 26 letters collided about
     * once in 26 runs, failing tests that had nothing to do with team naming —
     * the same shape of flake this suite has already been bitten by twice.
     */
    private static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => chr(ord('A') + (self::$sequence++ % 26)),
            'league_id' => League::find(1),
            'club_id' => Club::find(1),
            'captain_id' => User::find(1),
            'season_id' => Season::find(1),
        ];
    }
}
