<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\AgeCategoryEnum;
use Database\Seeders\DirectedTrainingDemoSeeder;

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('seeds 30 directed-training members split 10/10/10 by age', function (): void {
    $this->seed(DirectedTrainingDemoSeeder::class);

    $subs = Subscription::with('user')
        ->where('season_id', $this->season->id)
        ->where('wants_directed_training', true)
        ->get();

    expect($subs)->toHaveCount(30);

    $byAge = $subs->groupBy(fn ($s) => AgeCategoryEnum::fromBirthdate($s->user->birthdate)->value)
        ->map->count();

    expect($byAge[AgeCategoryEnum::CHILD->value])->toBe(10)
        ->and($byAge[AgeCategoryEnum::TEEN->value])->toBe(10)
        ->and($byAge[AgeCategoryEnum::ADULT->value])->toBe(10);
});

it('keeps the level modest — no ranking stronger than D4 (only NG/E/D)', function (): void {
    $this->seed(DirectedTrainingDemoSeeder::class);

    $rankings = Subscription::with('user')
        ->where('season_id', $this->season->id)
        ->where('wants_directed_training', true)
        ->get()
        ->pluck('user.ranking');

    // Allowed series only: unranked (NC), E, D — never C/B/A. And within D, max D4.
    $allowed = ['NC', 'E6', 'E4', 'E2', 'E0', 'D6', 'D4'];

    expect($rankings->every(fn (string $r): bool => in_array($r, $allowed, true)))->toBeTrue();
});

it('is idempotent — does not duplicate the cohort on a second run', function (): void {
    $this->seed(DirectedTrainingDemoSeeder::class);
    $this->seed(DirectedTrainingDemoSeeder::class);

    $count = Subscription::where('season_id', $this->season->id)
        ->where('wants_directed_training', true)
        ->count();

    expect($count)->toBe(30);
})->group('subscriptions');
