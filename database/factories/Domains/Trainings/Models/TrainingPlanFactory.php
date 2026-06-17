<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use App\Domains\Trainings\Models\TrainingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPlan>
 */
class TrainingPlanFactory extends Factory
{
    protected $model = TrainingPlan::class;

    public function archived(): self
    {
        return $this->state(fn (): array => [
            'status' => TrainingPlanStatusEnum::ARCHIVED->value,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => fake()->words(3, true),
            'status' => TrainingPlanStatusEnum::DRAFT->value,
            'created_by' => User::factory(),
        ];
    }
}
