<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionTrainingPack;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

pest()->group('trainings');

/*
 * Issue #67 asked for a typed pivot on subscription_training_pack, and warned
 * about the thing that makes it risky: seventeen raw
 * DB::table('subscription_training_pack') queries bypass Eloquent entirely, and
 * a typed hydration that started casting columns would hand every existing
 * reader a different type than it had yesterday.
 *
 * SubscriptionTrainingPack therefore declares no casts. These tests hold that
 * line: the pivot is typed for the analyser, and identical at runtime.
 */

function enrolledSubscription(): Subscription
{
    $subscription = Subscription::factory()->create();
    $pack = TrainingPack::factory()->create();

    $subscription->trainingPacks()->attach($pack->id, [
        'status' => 'enrolled',
        'starts_on' => '2026-10-01',
        'ends_on' => null,
        'override_amount' => 4500,
    ]);

    return $subscription;
}

it('hydrates the enrolment through the typed pivot', function (): void {
    $pack = enrolledSubscription()->trainingPacks()->first();

    expect($pack->pivot)->toBeInstanceOf(SubscriptionTrainingPack::class);
});

/*
 * The whole point of declaring no casts. A date that started arriving as a
 * Carbon would break every call site that hands it to a string-typed helper.
 */
it('returns the same attribute types it returned before', function (): void {
    $pack = enrolledSubscription()->trainingPacks()->first();

    expect($pack->pivot->starts_on)->toBeString()
        ->and($pack->pivot->starts_on)->toBe('2026-10-01')
        ->and($pack->pivot->status)->toBe('enrolled')
        ->and($pack->pivot->ends_on)->toBeNull()
        ->and((int) $pack->pivot->override_amount)->toBe(4500);
});

/* The seventeen raw readers must still see exactly what Eloquent wrote. */
it('writes rows the raw queries still read', function (): void {
    $subscription = enrolledSubscription();
    $packId = $subscription->trainingPacks()->first()->id;

    $raw = DB::table('subscription_training_pack')
        ->where('subscription_id', $subscription->id)
        ->where('training_pack_id', $packId)
        ->first();

    expect($raw->status)->toBe('enrolled')
        ->and($raw->starts_on)->toBe('2026-10-01')
        ->and((int) $raw->override_amount)->toBe(4500);
});

/* updateExistingPivot is the busiest writer in the domain; it must still land. */
it('still updates an existing row through the relation', function (): void {
    $subscription = enrolledSubscription();
    $packId = $subscription->trainingPacks()->first()->id;

    $subscription->trainingPacks()->updateExistingPivot($packId, [
        'status' => 'left',
        'ends_on' => '2026-12-31',
    ]);

    $fresh = $subscription->fresh()->trainingPacks()->first();

    expect($fresh->pivot->status)->toBe('left')
        ->and($fresh->pivot->ends_on)->toBe('2026-12-31');
});
