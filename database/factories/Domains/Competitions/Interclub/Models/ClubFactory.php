<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Competitions\Interclub\Models;

use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    protected $model = Club::class;

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
