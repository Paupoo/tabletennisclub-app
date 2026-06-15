<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\TrainingPlanStatusEnum;
use App\Domains\Trainings\Models\TrainingPack;
use App\Services\ClubAdmin\Planning\SeedTrainingPlanService;

function seedService(): SeedTrainingPlanService
{
    return app(SeedTrainingPlanService::class);
}

describe('SeedTrainingPlanService', function (): void {

    test('it creates a draft plan for the season', function (): void {
        $season = Season::factory()->create();
        $creator = User::factory()->create();

        $plan = seedService()->seedFromSeason($season, 'My scenario', $creator);

        expect($plan->season_id)->toBe($season->id)
            ->and($plan->name)->toBe('My scenario')
            ->and($plan->status)->toBe(TrainingPlanStatusEnum::DRAFT)
            ->and($plan->created_by)->toBe($creator->id);
    })->group('trainings', 'planning');

    test('it copies active packs with their source reference and ignores inactive ones', function (): void {
        $season = Season::factory()->create();
        $active = TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true, 'name' => 'Comp A', 'max_participants' => 8]);
        TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => false, 'name' => 'Old pack']);

        $plan = seedService()->seedFromSeason($season, 'Scenario', null);

        expect($plan->packs)->toHaveCount(1);
        $planPack = $plan->packs->first();
        expect($planPack->source_training_pack_id)->toBe($active->id)
            ->and($planPack->name)->toBe('Comp A')
            ->and($planPack->max_participants)->toBe(8)
            ->and($planPack->day_of_week)->toBe($active->day_of_week);
    })->group('trainings', 'planning');

    test('it copies real enrolments into the matching plan packs', function (): void {
        $season = Season::factory()->create();
        $pack = TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => $user->id]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $plan = seedService()->seedFromSeason($season, 'Scenario', null);
        $planPack = $plan->packs->first();

        expect($planPack->currentCount())->toBe(1)
            ->and($planPack->assignments->first()->user_id)->toBe($user->id);
    })->group('trainings', 'planning');

    test('it only copies enrolled (not pending/waiting) enrolments', function (): void {
        $season = Season::factory()->create();
        $pack = TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true]);

        $enrolled = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => User::factory()]);
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $pending = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => User::factory()]);
        $pending->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        $plan = seedService()->seedFromSeason($season, 'Scenario', null);
        $planPack = $plan->packs->first();

        // The pending member is not in the pack but lands in the pool.
        expect($planPack->currentCount())->toBe(1)
            ->and($planPack->assignments->first()->user_id)->toBe($enrolled->user_id);

        $pool = $plan->assignments()->whereNull('training_plan_pack_id')->pluck('user_id');
        expect($pool)->toContain($pending->user_id);
    })->group('trainings', 'planning');

    test('season members without an enrolled pack land in the pool', function (): void {
        $season = Season::factory()->create();
        TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true]);

        $withoutPack = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => User::factory()]);

        $plan = seedService()->seedFromSeason($season, 'Scenario', null);

        $pool = $plan->assignments()->whereNull('training_plan_pack_id')->pluck('user_id');
        expect($pool)->toContain($withoutPack->user_id);
    })->group('trainings', 'planning');

    test('a member enrolled in two packs is assigned only once', function (): void {
        $season = Season::factory()->create();
        $packA = TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true]);
        $packB = TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true]);

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => $user->id]);
        $subscription->trainingPacks()->attach($packA->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($packB->id, ['status' => 'enrolled']);

        $plan = seedService()->seedFromSeason($season, 'Scenario', null);

        expect($plan->assignments()->where('user_id', $user->id)->count())->toBe(1);
    })->group('trainings', 'planning');

    test('every season member appears exactly once in the plan', function (): void {
        $season = Season::factory()->create();
        $pack = TrainingPack::factory()->create(['season_id' => $season->id, 'is_active' => true]);

        $enrolled = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => User::factory()]);
        $enrolled->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);
        $poolMember = Subscription::factory()->create(['season_id' => $season->id, 'user_id' => User::factory()]);

        // A subscription from another season must be ignored.
        $otherSeason = Season::factory()->create();
        Subscription::factory()->create(['season_id' => $otherSeason->id, 'user_id' => User::factory()]);

        $plan = seedService()->seedFromSeason($season, 'Scenario', null);

        $userIds = $plan->assignments()->pluck('user_id');
        expect($userIds)->toHaveCount(2)
            ->and($userIds->unique())->toHaveCount(2)
            ->and($userIds)->toContain($enrolled->user_id)
            ->and($userIds)->toContain($poolMember->user_id);
    })->group('trainings', 'planning');
});
