<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Users\Models;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => (string) fake()->numberBetween(460000000, 499000000),
            'email' => fake()->unique()->safeEmail(),
            'iban' => null,
        ];
    }
}
