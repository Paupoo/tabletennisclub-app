<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Table>
 */
class TableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numberBetween(1, 20),
            'purchased_on' => fake()->dateTimeBetween('-10 years', '-1 year'),
            'state' => 'used',
            'room_id' => Room::first()->limit(1)->get(),
        ];
    }
}
