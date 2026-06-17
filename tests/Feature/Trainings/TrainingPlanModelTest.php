<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Models\TrainingPlan;
use App\Domains\Trainings\Models\TrainingPlanAssignment;
use App\Domains\Trainings\Models\TrainingPlanPack;
use Illuminate\Database\QueryException;

describe('TrainingPlan model', function (): void {

    test('it creates a plan with default draft status and relations', function (): void {
        $season = Season::factory()->create();
        $creator = User::factory()->create();

        $plan = TrainingPlan::factory()->create([
            'season_id' => $season->id,
            'created_by' => $creator->id,
        ]);

        expect($plan->fresh())
            ->status->toBe(TrainingPlanStatusEnum::DRAFT)
            ->and($plan->season->is($season))->toBeTrue()
            ->and($plan->creator->is($creator))->toBeTrue();
    })->group('trainings', 'planning');

    test('status is cast to the enum', function (): void {
        $plan = TrainingPlan::factory()->archived()->create();

        expect($plan->fresh()->status)->toBe(TrainingPlanStatusEnum::ARCHIVED);
    })->group('trainings', 'planning');

    test('it exposes packs and assignments relations', function (): void {
        $plan = TrainingPlan::factory()->create();
        $pack = TrainingPlanPack::factory()->for($plan, 'plan')->create();
        TrainingPlanAssignment::factory()
            ->for($plan, 'plan')
            ->for($pack, 'pack')
            ->create();

        expect($plan->packs)->toHaveCount(1)
            ->and($plan->assignments)->toHaveCount(1);
    })->group('trainings', 'planning');

    test('deleting the creator nulls created_by', function (): void {
        $creator = User::factory()->create();
        $plan = TrainingPlan::factory()->create(['created_by' => $creator->id]);

        $creator->forceDelete();

        expect($plan->fresh()->created_by)->toBeNull();
    })->group('trainings', 'planning');
});

describe('TrainingPlanPack model', function (): void {

    test('hypothetical state has no source pack', function (): void {
        $pack = TrainingPlanPack::factory()->hypothetical()->create();

        expect($pack->source_training_pack_id)->toBeNull()
            ->and($pack->sourcePack)->toBeNull();
    })->group('trainings', 'planning');

    test('source pack relation resolves the real training pack', function (): void {
        $source = TrainingPack::factory()->create();
        $pack = TrainingPlanPack::factory()->create(['source_training_pack_id' => $source->id]);

        expect($pack->sourcePack->is($source))->toBeTrue();
    })->group('trainings', 'planning');

    test('currentCount reflects the number of assignments', function (): void {
        $pack = TrainingPlanPack::factory()->create();
        TrainingPlanAssignment::factory()->count(3)
            ->for($pack->plan, 'plan')
            ->for($pack, 'pack')
            ->create();

        expect($pack->currentCount())->toBe(3);
    })->group('trainings', 'planning');

    test('isOverCapacity is false under and at capacity, true above', function (): void {
        $pack = TrainingPlanPack::factory()->withCapacity(2)->create();

        expect($pack->isOverCapacity())->toBeFalse();

        TrainingPlanAssignment::factory()->count(2)
            ->for($pack->plan, 'plan')->for($pack, 'pack')->create();
        expect($pack->fresh()->isOverCapacity())->toBeFalse();

        TrainingPlanAssignment::factory()
            ->for($pack->plan, 'plan')->for($pack, 'pack')->create();
        expect($pack->fresh()->isOverCapacity())->toBeTrue();
    })->group('trainings', 'planning');

    test('isOverCapacity is always false when capacity is null', function (): void {
        $pack = TrainingPlanPack::factory()->withCapacity(null)->create();
        TrainingPlanAssignment::factory()->count(5)
            ->for($pack->plan, 'plan')->for($pack, 'pack')->create();

        expect($pack->fresh()->isOverCapacity())->toBeFalse();
    })->group('trainings', 'planning');
});

describe('TrainingPlanAssignment model', function (): void {

    test('a pool assignment has no pack', function (): void {
        $assignment = TrainingPlanAssignment::factory()->inPool()->create();

        expect($assignment->training_plan_pack_id)->toBeNull()
            ->and($assignment->pack)->toBeNull();
    })->group('trainings', 'planning');

    test('it resolves plan, pack and user relations', function (): void {
        $assignment = TrainingPlanAssignment::factory()->create();

        expect($assignment->plan)->not->toBeNull()
            ->and($assignment->pack)->not->toBeNull()
            ->and($assignment->user)->not->toBeNull();
    })->group('trainings', 'planning');

    test('a user can only be assigned once per plan', function (): void {
        $plan = TrainingPlan::factory()->create();
        $user = User::factory()->create();

        TrainingPlanAssignment::factory()->inPool()
            ->for($plan, 'plan')->for($user, 'user')->create();

        TrainingPlanAssignment::factory()->inPool()
            ->for($plan, 'plan')->for($user, 'user')->create();
    })->throws(QueryException::class)->group('trainings', 'planning');
});

describe('TrainingPlanStatusEnum', function (): void {

    test('values returns the backing strings', function (): void {
        expect(TrainingPlanStatusEnum::values())->toBe(['archived', 'draft']);
    })->group('trainings', 'planning');

    test('every case has a non-empty label', function (): void {
        foreach (TrainingPlanStatusEnum::cases() as $case) {
            expect($case->getLabel())->not->toBe('');
        }
    })->group('trainings', 'planning');
});
