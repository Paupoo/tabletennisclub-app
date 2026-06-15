<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Planning;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a draft {@see TrainingPlan} from the real state of a season:
 * its active training packs become plan packs, the real enrolments become
 * assignments, and every other season member lands in the unassigned pool.
 *
 * This is a one-shot snapshot used as a decision artefact — it never writes
 * back to the real packs or enrolments (decisions #8/#9/#10).
 */
class SeedTrainingPlanService
{
    /**
     * Build a draft plan mirroring the season's real offer and enrolments.
     */
    public function seedFromSeason(Season $season, string $name, ?User $creator = null): TrainingPlan
    {
        return DB::transaction(function () use ($season, $name, $creator): TrainingPlan {
            $plan = TrainingPlan::query()->create([
                'season_id' => $season->id,
                'name' => $name,
                'status' => TrainingPlanStatusEnum::DRAFT->value,
                'created_by' => $creator?->id,
            ]);

            $packMap = $this->copyActivePacks($season, $plan);
            $assignedUserIds = $this->copyEnrolments($season, $plan, $packMap);
            $this->fillPool($season, $plan, $assignedUserIds);

            return $plan;
        });
    }

    /**
     * Copy every active training pack of the season into the plan.
     *
     * @return array<int, int> map of source TrainingPack id => TrainingPlanPack id
     */
    private function copyActivePacks(Season $season, TrainingPlan $plan): array
    {
        $packMap = [];
        $position = 0;

        $packs = TrainingPack::query()
            ->where('season_id', $season->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        foreach ($packs as $pack) {
            $planPack = TrainingPlanPack::query()->create([
                'training_plan_id' => $plan->id,
                'source_training_pack_id' => $pack->id,
                'name' => $pack->name,
                'level' => $pack->level?->value,
                'day_of_week' => $pack->day_of_week,
                'max_participants' => $pack->max_participants,
                'position' => $position++,
            ]);

            $packMap[$pack->id] = $planPack->id;
        }

        return $packMap;
    }

    /**
     * Copy the real `enrolled` enrolments of the season into the matching plan packs.
     *
     * @param  array<int, int>  $packMap  source TrainingPack id => TrainingPlanPack id
     * @return array<int, int> user ids already assigned to a pack
     */
    private function copyEnrolments(Season $season, TrainingPlan $plan, array $packMap): array
    {
        if ($packMap === []) {
            return [];
        }

        $subscriptions = Subscription::query()
            ->where('season_id', $season->id)
            ->with(['trainingPacks' => function ($query): void {
                $query->wherePivot('status', 'enrolled');
            }])
            ->get();

        $assignedUserIds = [];
        $positions = [];

        foreach ($subscriptions as $subscription) {
            $userId = $subscription->user_id;

            foreach ($subscription->trainingPacks as $pack) {
                // Skip inactive packs (not copied) and de-duplicate per user.
                if (! isset($packMap[$pack->id]) || isset($assignedUserIds[$userId])) {
                    continue;
                }

                $planPackId = $packMap[$pack->id];
                $position = $positions[$planPackId] ?? 0;

                $plan->assignments()->create([
                    'training_plan_pack_id' => $planPackId,
                    'user_id' => $userId,
                    'position' => $position,
                ]);

                $positions[$planPackId] = $position + 1;
                $assignedUserIds[$userId] = $userId;
            }
        }

        return $assignedUserIds;
    }

    /**
     * Put every remaining season member into the pool, but only those who opted in
     * to directed training (`wants_directed_training = true`). Members who are not
     * interested are ignored — they never reach the planning board (decision #11).
     *
     * Members already assigned to a real pack are copied as-is upstream and skipped here.
     *
     * @param  array<int, int>  $assignedUserIds
     */
    private function fillPool(Season $season, TrainingPlan $plan, array $assignedUserIds): void
    {
        $poolUserIds = Subscription::query()
            ->where('season_id', $season->id)
            ->wantsDirectedTraining()
            ->whereNotIn('user_id', array_values($assignedUserIds))
            ->distinct()
            ->pluck('user_id');

        $position = 0;

        foreach ($poolUserIds as $userId) {
            $plan->assignments()->create([
                'training_plan_pack_id' => null,
                'user_id' => $userId,
                'position' => $position++,
            ]);
        }
    }
}
