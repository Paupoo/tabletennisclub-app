<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Planning;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Suggests a homogeneous layout for a {@see TrainingPlan}: it places the
 * pool members (assignments with a null pack) into the existing packs using a
 * best-fit greedy heuristic. Non-destructive — members already in a group are
 * never moved, no pack is ever created, and the surplus stays in the pool.
 *
 * Priority of the criteria (locked decision):
 *   1. level homogeneity (ranking)               — weight {@see self::W_LEVEL}
 *   2. age separation (do not mix kids/adults)    — weight {@see self::W_AGE}
 *   3. declared pack level/day match (soft bonus) — weight {@see self::W_DECLARED}
 *
 * with W_LEVEL ≫ W_AGE > W_DECLARED, so level dominates, age breaks ties, and
 * the declared pack level/day only nudges, never blocks. Capacity is a hard
 * constraint (full packs are skipped).
 */
class OptimizeTrainingPlanService
{
    /**
     * Ranking series strength (higher = stronger). NC / NA / unknown is the floor.
     */
    private const array SERIES_STRENGTH = [
        'A' => 5,
        'B' => 4,
        'C' => 3,
        'D' => 2,
        'E' => 1,
    ];

    /** Age category mismatch penalty (one notch ≈ 10 ranking points). */
    private const float W_AGE = 10.0;

    /** Soft penalty when the candidate's level does not match the pack's declared level. */
    private const float W_DECLARED = 1.0;

    /** Level homogeneity dominates everything else. */
    private const float W_LEVEL = 100.0;

    /**
     * Cost of opening an empty pack rather than joining a non-empty one. Sized
     * (≈ 10 ranking points × {@see self::W_LEVEL}) so a candidate prefers an
     * existing pack of the *same series* but still opens a fresh pack when the
     * only alternative is a different series. Keeps same-level members together.
     */
    private const float W_OPEN = 1000.0;

    /**
     * Fill the pool of a plan into its packs, in place.
     *
     * @return array{assigned: int, left_in_pool: int}
     */
    public function optimize(TrainingPlan $plan): array
    {
        return DB::transaction(function () use ($plan): array {
            $plan->loadMissing(['packs', 'assignments.user']);

            $packs = $this->buildPackStates($plan);

            /** @var Collection<int, TrainingPlanAssignment> $pool */
            $pool = $plan->assignments
                ->whereNull('training_plan_pack_id')
                ->sortByDesc(fn (TrainingPlanAssignment $a): int => $this->rankingValue($a->user->ranking))
                ->values();

            $assigned = 0;

            foreach ($pool as $assignment) {
                $target = $this->bestPackFor($assignment->user, $packs);

                if ($target === null) {
                    continue; // no pack with remaining capacity → stays in the pool
                }

                $position = $target['count'];

                $assignment->update([
                    'training_plan_pack_id' => $target['id'],
                    'position' => $position,
                ]);

                $this->absorb($packs, $target['id'], $assignment->user);
                $assigned++;
            }

            return [
                'assigned' => $assigned,
                'left_in_pool' => $pool->count() - $assigned,
            ];
        });
    }

    /**
     * Map a ranking string to a monotonic integer (higher = stronger).
     *
     * Series order A > B > C > D > E > NC; within a series a smaller number is
     * stronger (B0 > B6, D0 > D6). Value = series*10 + (10 - number). NC / NA /
     * empty / unknown is the lowest (0).
     */
    public function rankingValue(?string $ranking): int
    {
        $ranking = strtoupper(trim((string) $ranking));

        $series = substr($ranking, 0, 1);

        if (! isset(self::SERIES_STRENGTH[$series])) {
            return 0;
        }

        $number = (int) substr($ranking, 1);
        $number = max(0, min(9, $number));

        return self::SERIES_STRENGTH[$series] * 10 + (10 - $number);
    }

    /**
     * Update a pack state after a member is assigned to it.
     *
     * @param  array<int, array<string, mixed>>  $packs
     */
    private function absorb(array &$packs, int $packId, User $user): void
    {
        $packs[$packId]['count']++;
        $packs[$packId]['level_sum'] += $this->rankingValue($user->ranking);
        $packs[$packId]['level_count']++;
        $this->bumpAge($packs[$packId]['age_counts'], $this->ageCategory($user));
    }

    /**
     * Age category value for a user, or 'unknown' when no birthdate is known.
     */
    private function ageCategory(User $user): string
    {
        return $user->birthdate !== null
            ? AgeCategoryEnum::fromBirthdate($user->birthdate)->value
            : 'unknown';
    }

    /**
     * Pick the lowest-cost pack with remaining capacity for the candidate, or
     * null when every pack is full.
     *
     * @param  array<int, array<string, mixed>>  $packs
     * @return array<string, mixed>|null
     */
    private function bestPackFor(User $candidate, array $packs): ?array
    {
        $candidateValue = $this->rankingValue($candidate->ranking);
        $candidateAge = $this->ageCategory($candidate);

        $best = null;
        $bestCost = null;

        foreach ($packs as $state) {
            if ($state['max'] !== null && $state['count'] >= $state['max']) {
                continue; // hard capacity constraint
            }

            $cost = $this->cost($candidateValue, $candidateAge, $state);

            if ($bestCost === null || $cost < $bestCost) {
                $bestCost = $cost;
                $best = $state;
            }
        }

        return $best;
    }

    /**
     * Build the mutable per-pack state used while assigning.
     *
     * Each state primes its level average from its placed members; if empty, it
     * falls back to the declared level, otherwise stays neutral (null).
     *
     * @return array<int, array{
     *   id: int, max: int|null, count: int, level_sum: int, level_count: int,
     *   declared_value: int|null, age_counts: array<string, int>
     * }>
     */
    private function buildPackStates(TrainingPlan $plan): array
    {
        $assignmentsByPack = $plan->assignments
            ->whereNotNull('training_plan_pack_id')
            ->groupBy('training_plan_pack_id');

        $states = [];

        foreach ($plan->packs as $pack) {
            /** @var TrainingPlanPack $pack */
            $members = $assignmentsByPack->get($pack->id, collect());

            $levelSum = 0;
            $levelCount = 0;
            $ageCounts = [];

            foreach ($members as $assignment) {
                /** @var TrainingPlanAssignment $assignment */
                $levelSum += $this->rankingValue($assignment->user->ranking);
                $levelCount++;
                $this->bumpAge($ageCounts, $this->ageCategory($assignment->user));
            }

            $states[$pack->id] = [
                'id' => $pack->id,
                'max' => $pack->max_participants,
                'count' => $members->count(),
                'level_sum' => $levelSum,
                'level_count' => $levelCount,
                'declared_value' => $pack->level !== null ? $this->rankingValue($pack->level) : null,
                'age_counts' => $ageCounts,
            ];
        }

        return $states;
    }

    /**
     * @param  array<string, int>  $ageCounts
     */
    private function bumpAge(array &$ageCounts, string $category): void
    {
        $ageCounts[$category] = ($ageCounts[$category] ?? 0) + 1;
    }

    /**
     * Cost of placing a candidate into a pack — lower is better.
     *
     * @param  array<string, mixed>  $state
     */
    private function cost(int $candidateValue, string $candidateAge, array $state): float
    {
        // 1. Level homogeneity: distance to the pack's current level average.
        $isEmpty = $state['level_count'] === 0;
        $packLevel = $isEmpty
            ? ($state['declared_value'] ?? $candidateValue) // empty pack with no declared level = neutral
            : $state['level_sum'] / $state['level_count'];

        $levelCost = self::W_LEVEL * abs($candidateValue - $packLevel);

        // Prefer joining a non-empty pack of the same level over opening a fresh
        // one, while still opening one when the alternative is a different series.
        $openCost = $isEmpty ? self::W_OPEN : 0.0;

        // 2. Age separation: penalise when the dominant age differs.
        $dominantAge = $this->dominantAge($state['age_counts']);
        $ageCost = ($dominantAge !== null && $dominantAge !== $candidateAge)
            ? self::W_AGE
            : 0.0;

        // 3. Declared pack level (sub-weighted soft nudge).
        $declaredCost = $state['declared_value'] !== null
            ? self::W_DECLARED * abs($candidateValue - $state['declared_value'])
            : 0.0;

        return $levelCost + $openCost + $ageCost + $declaredCost;
    }

    /**
     * Resolve the dominant age category of a pack, or null when empty.
     *
     * @param  array<string, int>  $ageCounts
     */
    private function dominantAge(array $ageCounts): ?string
    {
        if ($ageCounts === []) {
            return null;
        }

        arsort($ageCounts);

        return array_key_first($ageCounts);
    }
}
