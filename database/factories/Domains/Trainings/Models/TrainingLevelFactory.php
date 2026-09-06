<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\Trainings\Models\TrainingLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingLevel>
 */
class TrainingLevelFactory extends Factory
{
    protected $model = TrainingLevel::class;

    public function definition(): array
    {
        return [
            'label' => ucfirst($this->faker->unique()->word()),
            'color' => $this->faker->randomElement(['primary', 'success', 'warning', 'error', 'info']),
            'position' => TrainingLevel::max('position') + 1,
            'is_active' => true,
        ];
    }

    public function retired(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
