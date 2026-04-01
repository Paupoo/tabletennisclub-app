<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Tournament;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClubEvents\Tournament\Tournament>
 */
class TournamentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween(now(), '+ 15 days');
        $start_date = Carbon::parse($date)->roundMinute(1);
        $end_date = Carbon::parse($start_date)->addHour(8);

        return [
            'name' => fake()->name(),
            'description' => fake()->paragraph(),
            'location' => fake()->address(),
            'image' => fake()->imageUrl(400, 300),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'max_users' => fake()->randomNumber(2),
            'price' => fake()->randomFloat(2, 10, 30),
        ];
    }
}