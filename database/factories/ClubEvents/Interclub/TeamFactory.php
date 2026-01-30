<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Interclub;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Interclub\League;
use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClubEvents\Interclub\Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'name' => strtoupper(fake()->randomLetter()),
            'league_id' => League::find(1),
            'club_id' => Club::find(1),
            'captain_id' => User::find(1),
            'season_id' => Season::find(1),
        ];
    }
}
