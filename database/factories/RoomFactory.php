<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $building = fake()->randomElement(['Centre sportif', 'Stade', 'Hall omnisport']);
        $name = fake()->name();
        $floor = fake()->randomElement([null, '0', '-1', '1', '2']);

        return [
            'name' => fake()->unique()->randomElement([$name . $floor, fake()->randomLetter() . fake()->numberBetween(1, 20)]),
            'building_name' => $building . ' ' . $name,
            'street' => fake()->streetAddress(),
            'city_code' => '13' . fake()->randomNumber(2, 2),
            'city_name' => fake()->city(),
            'floor' => $floor,
            'access_description' => fake()->text(150),
            'capacity_for_trainings' => fake()->numberBetween(2, 10),
            'capacity_for_interclubs' => fake()->numberBetween(2, 10),
        ];

    }
}
