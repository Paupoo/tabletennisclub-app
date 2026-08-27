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
            // unique(), not randomNumber(3): the column is unique and the draw had
            // a thousand values, so two clubs in one test collided now and then —
            // a red build with nothing wrong in the code under test.
            'licence' => 'BBW' . fake()->unique()->numberBetween(100, 999),
            'street' => fake()->streetAddress(),
            'city_code' => '13' . fake()->randomNumber(2, true),
            'city_name' => fake()->city(),
        ];
    }

    public function ownClub(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'C.T.T Ottignies-Blocry',
            'is_own_club' => true,
            'bic' => 'CREGBEBB',
            'bank_account' => 'BE23732333208791',
        ]);
    }
}
