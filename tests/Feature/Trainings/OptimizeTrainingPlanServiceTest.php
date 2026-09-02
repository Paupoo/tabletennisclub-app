<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use App\Services\ClubAdmin\Planning\OptimizeTrainingPlanService;

function optimizeService(): OptimizeTrainingPlanService
{
    return app(OptimizeTrainingPlanService::class);
}

/**
 * Create a pooled assignment (training_plan_pack_id null) for a fresh member
 * with the given ranking/birthdate, plus a season subscription.
 */
function poolMember(TrainingPlan $plan, int $seasonId, ?string $ranking, $birthdate): TrainingPlanAssignment
{
    $user = User::factory()->create([
        'ranking' => $ranking,
        'birthdate' => $birthdate,
    ]);
    Subscription::factory()->create([
        'user_id' => $user->id,
        'season_id' => $seasonId,
        'status' => 'paid',
    ]);

    return TrainingPlanAssignment::factory()->for($plan, 'plan')->inPool()->create([
        'user_id' => $user->id,
    ]);
}

describe('ranking value mapping', function (): void {
    it('orders rankings monotonically: A1 > B0 > B6 > C0 > D0 > E6 > NC', function (): void {
        $service = optimizeService();

        $a1 = $service->rankingValue('A1');
        $b0 = $service->rankingValue('B0');
        $b6 = $service->rankingValue('B6');
        $c0 = $service->rankingValue('C0');
        $d0 = $service->rankingValue('D0');
        $e6 = $service->rankingValue('E6');
        $ng = $service->rankingValue('NC');

        expect($a1)->toBeGreaterThan($b0)
            ->and($b0)->toBeGreaterThan($b6)
            ->and($b6)->toBeGreaterThan($c0)
            ->and($c0)->toBeGreaterThan($d0)
            ->and($d0)->toBeGreaterThan($e6)
            ->and($e6)->toBeGreaterThan($ng);
    });

    it('treats empty and unknown rankings as the lowest (NC) value', function (): void {
        $service = optimizeService();

        expect($service->rankingValue(''))->toBe($service->rankingValue('NC'))
            ->and($service->rankingValue(null))->toBe($service->rankingValue('NC'))
            ->and($service->rankingValue('e6'))->toBe($service->rankingValue('E6'));
    });
})->group('trainings');

describe('OptimizeTrainingPlanService', function (): void {
    beforeEach(function (): void {
        $this->season = makeActiveSeason();
    });

    it('never moves members already placed in a group', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $packA = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 10]);
        $packB = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 10]);

        // Already placed in packA — must not move.
        $placedUser = User::factory()->create(['ranking' => 'D0', 'birthdate' => now()->subYears(30)]);
        Subscription::factory()->create(['user_id' => $placedUser->id, 'season_id' => $this->season->id, 'status' => 'paid']);
        $placed = TrainingPlanAssignment::factory()->for($plan, 'plan')->for($packA, 'pack')->create([
            'user_id' => $placedUser->id,
        ]);

        poolMember($plan, $this->season->id, 'B0', now()->subYears(30));

        optimizeService()->optimize($plan);

        expect($placed->fresh()->training_plan_pack_id)->toBe($packA->id);
    })->group('trainings');

    it('respects capacity and leaves the surplus in the pool', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 2]);

        // 3 pooled members, same level/age → all want the single pack (capacity 2).
        poolMember($plan, $this->season->id, 'C0', now()->subYears(30));
        poolMember($plan, $this->season->id, 'C0', now()->subYears(30));
        poolMember($plan, $this->season->id, 'C0', now()->subYears(30));

        $result = optimizeService()->optimize($plan);

        expect($result['assigned'])->toBe(2)
            ->and($result['left_in_pool'])->toBe(1);

        $inPool = $plan->assignments()->whereNull('training_plan_pack_id')->count();
        expect($inPool)->toBe(1);
    })->group('trainings');

    it('groups members homogeneously by level', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $packA = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 2]);
        $packB = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 2]);

        // Two strong (B) and two weak (D) members, all adults.
        $b1 = poolMember($plan, $this->season->id, 'B0', now()->subYears(30));
        $b2 = poolMember($plan, $this->season->id, 'B2', now()->subYears(30));
        $d1 = poolMember($plan, $this->season->id, 'D0', now()->subYears(30));
        $d2 = poolMember($plan, $this->season->id, 'D6', now()->subYears(30));

        optimizeService()->optimize($plan);

        // The two B players end up together, the two D players together.
        $bPack = $b1->fresh()->training_plan_pack_id;
        $dPack = $d1->fresh()->training_plan_pack_id;

        expect($b2->fresh()->training_plan_pack_id)->toBe($bPack)
            ->and($d2->fresh()->training_plan_pack_id)->toBe($dPack)
            ->and($bPack)->not->toBe($dPack)
            ->and([$bPack, $dPack])->toContain($packA->id)
            ->and([$bPack, $dPack])->toContain($packB->id);
    })->group('trainings');

    it('separates age categories when an alternative exists', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        $packA = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 2]);
        $packB = TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => 2]);

        // Two adults and two children, all the same level → level cannot separate
        // them, so age must. Each pack should end up age-homogeneous.
        $adult1 = poolMember($plan, $this->season->id, 'C0', now()->subYears(35));
        $adult2 = poolMember($plan, $this->season->id, 'C0', now()->subYears(40));
        $child1 = poolMember($plan, $this->season->id, 'C0', now()->subYears(10));
        $child2 = poolMember($plan, $this->season->id, 'C0', now()->subYears(11));

        optimizeService()->optimize($plan);

        $adultPack = $adult1->fresh()->training_plan_pack_id;
        $childPack = $child1->fresh()->training_plan_pack_id;

        expect($adult2->fresh()->training_plan_pack_id)->toBe($adultPack)
            ->and($child2->fresh()->training_plan_pack_id)->toBe($childPack)
            ->and($adultPack)->not->toBe($childPack);
    })->group('trainings');

    it('accepts unlimited capacity packs (null max)', function (): void {
        $plan = TrainingPlan::factory()->create(['season_id' => $this->season->id]);
        TrainingPlanPack::factory()->for($plan, 'plan')->create(['max_participants' => null]);

        poolMember($plan, $this->season->id, 'B0', now()->subYears(30));
        poolMember($plan, $this->season->id, 'B2', now()->subYears(30));
        poolMember($plan, $this->season->id, 'B4', now()->subYears(30));

        $result = optimizeService()->optimize($plan);

        expect($result['assigned'])->toBe(3)
            ->and($result['left_in_pool'])->toBe(0);
    })->group('trainings');
});
