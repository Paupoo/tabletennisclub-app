<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Carbon;

it('dates the affiliation the day the committee validates it', function (): void {
    $subscription = Subscription::factory()->pending()->for(User::factory())->create([
        'season_id' => makeActiveSeason()->id,
    ]);

    expect($subscription->confirmed_at)->toBeNull();

    Carbon::setTestNow('2026-09-14 18:30:00');
    $subscription->confirm();

    expect($subscription->fresh()->confirmed_at->toDateTimeString())->toBe('2026-09-14 18:30:00');
});

it('dates an affiliation created already validated, as an import does', function (): void {
    Carbon::setTestNow('2026-08-20 08:00:00');

    $subscription = Subscription::factory()->for(User::factory())->create([
        'season_id' => makeActiveSeason()->id,
        'status' => 'confirmed',
    ]);

    expect($subscription->confirmed_at->toDateTimeString())->toBe('2026-08-20 08:00:00');
});

it('keeps the first date when an affiliation is validated again', function (): void {
    Carbon::setTestNow('2026-09-14 18:30:00');

    $subscription = Subscription::factory()->pending()->for(User::factory())->create([
        'season_id' => makeActiveSeason()->id,
    ]);
    $subscription->confirm();

    Carbon::setTestNow('2026-12-01 09:00:00');
    $subscription->markAsPaid();

    expect($subscription->fresh()->confirmed_at->toDateTimeString())->toBe('2026-09-14 18:30:00');
});

it('leaves a pending request undated', function (): void {
    $subscription = Subscription::factory()->pending()->for(User::factory())->create([
        'season_id' => makeActiveSeason()->id,
    ]);

    expect($subscription->fresh()->confirmed_at)->toBeNull();
});
