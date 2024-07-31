<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\League;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Team>
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
