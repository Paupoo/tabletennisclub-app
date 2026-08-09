<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Fines\Models;

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fine>
 */
class FineFactory extends Factory
{
    protected $model = Fine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'issued_by' => User::factory(),
            'amount' => fake()->randomElement([10, 15, 25, 50]),
            'reason' => fake()->randomElement(FineReason::cases()),
            'federation_reference' => fake()->optional()->bothify('AFTTB-####'),
            'description' => fake()->optional()->sentence(),
            'pedagogical_message' => fake()->paragraph(),
        ];
    }
}
