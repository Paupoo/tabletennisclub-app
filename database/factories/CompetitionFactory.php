<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Competition>
 */
class CompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {

        $clubs = [
            'Auderghem',
            'Palette bleue',
            'Hamme-Mille',
            'La Hulpe - Rixensart',
            'Set-Jet Fleur Bleue',
            'CTT Royal Alpa',
            'REP Nivellois',
            'Arc en ciel',
            'CTT Braine-l\'Alleud',
            'TT Zenith Brussels',
            'TT Perwez',
        ];

        $teams = [
            'A',
            'B',
            'C',
            'D',
            'E',
            'F',
            'G',
        ];

        $date = today()->next('Friday')->addWeeks(rand(1, 10))->addHour(19);

        return [
            'competition_date' => $date,
            'address' => fake()->address(),
            'total_players' => 4,
            'week_number' => $date->weekOfYear,
            'team_id' => 2,
            'opposing_team' => sprintf('%1$s %2$s', $clubs[array_rand($clubs)], $teams[array_rand($teams)]),
        ];
    }
}
