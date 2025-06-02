<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Interclub>
 */
class InterclubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'address' => 'rue de l\'Invasion 80, 1340 Ottignies',
            'start_date_time' => '2024-08-12T19:45',
            'total_players' => 4,
            'week_number' => 15,
        ];
    }
}
