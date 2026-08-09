<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Club\Models;

use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\Shared\Enums\TableStateEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Table>
 */
class TableFactory extends Factory
{
    protected $model = Table::class;

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
            'state' => TableStateEnum::GOOD,
            'room_id' => null,
        ];
    }
}
