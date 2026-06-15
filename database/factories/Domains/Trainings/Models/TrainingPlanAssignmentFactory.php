<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Trainings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPlanAssignment>
 */
class TrainingPlanAssignmentFactory extends Factory
{
    protected $model = TrainingPlanAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plan = TrainingPlan::factory();

        return [
            'training_plan_id' => $plan,
            'training_plan_pack_id' => TrainingPlanPack::factory()->for($plan, 'plan'),
            'user_id' => User::factory(),
            'position' => 0,
        ];
    }

    /**
     * An assignment sitting in the unassigned pool (no pack).
     */
    public function inPool(): self
    {
        return $this->state(fn (): array => [
            'training_plan_pack_id' => null,
        ]);
    }
}
