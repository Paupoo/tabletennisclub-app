<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPlanPack>
 */
class TrainingPlanPackFactory extends Factory
{
    protected $model = TrainingPlanPack::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'training_plan_id' => TrainingPlan::factory(),
            // Hypothetical by default: avoids eagerly creating a TrainingPack
            // (and a random-dated Season that can overlap the active season
            // under parallel tests). Use fromPack() for a real source.
            'source_training_pack_id' => null,
            'name' => fake()->words(2, true),
            'level' => null,
            'day_of_week' => fake()->numberBetween(1, 7),
            'max_participants' => fake()->numberBetween(4, 12),
            'position' => 0,
        ];
    }

    /**
     * A pack mirroring a real training pack (creates one if none given).
     */
    public function fromPack(?TrainingPack $pack = null): self
    {
        return $this->state(fn (): array => [
            'source_training_pack_id' => $pack?->id ?? TrainingPack::factory(),
        ]);
    }

    /**
     * A hypothetical pack with no real source training pack.
     */
    public function hypothetical(): self
    {
        return $this->state(fn (): array => [
            'source_training_pack_id' => null,
        ]);
    }

    public function withCapacity(?int $max): self
    {
        return $this->state(fn (): array => [
            'max_participants' => $max,
        ]);
    }
}
