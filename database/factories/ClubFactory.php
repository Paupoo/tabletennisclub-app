<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Club>
 */
class ClubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'C.T.T. ' . fake()->city(),
            'is_active' => true,
            'licence' => 'BBW' . fake()->randomNumber(3),
            'street' => fake()->streetAddress(),
            'city_code' => '13' . fake()->randomNumber(2, true),
            'city_name' => fake()->city(),
        ];
    }
}
