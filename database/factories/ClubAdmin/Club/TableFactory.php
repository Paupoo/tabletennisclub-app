<?php

declare(strict_types=1);

namespace Database\Factories\ClubAdmin\Club;

use App\Models\ClubAdmin\Club\Room;
use App\Models\ClubAdmin\Club\Table;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
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
            'room_id' => Room::first()->get(),
        ];
    }
}
