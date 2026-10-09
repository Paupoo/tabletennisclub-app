<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\ExternalParticipants\Models;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalRegistration>
 */
class ExternalRegistrationFactory extends Factory
{
    protected $model = ExternalRegistration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'registrable_type' => TrainingPack::class,
            'registrable_id' => TrainingPack::factory()->camp(),
            'status' => 'enrolled',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'is_minor' => false,
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
        ];
    }

    /**
     * A child, reached through the adult who answers for them.
     */
    public function minor(): self
    {
        return $this->state(fn (): array => [
            'is_minor' => true,
            'guardian_first_name' => fake()->firstName(),
            'guardian_last_name' => fake()->lastName(),
            'guardian_phone' => fake()->phoneNumber(),
        ]);
    }
}
