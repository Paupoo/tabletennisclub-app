<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;

function runBackfillSubscriptionConfirmedAtMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_12_014104_backfill_subscription_confirmed_at.php');
    $migration->up();
}

/**
 * @param  array<string, mixed>  $attributes
 */
function subscriptionWithoutConfirmedAt(array $attributes = []): Subscription
{
    $subscription = Subscription::factory()->for(User::factory())->create(array_merge([
        'season_id' => makeActiveSeason()->id,
        'status' => 'confirmed',
    ], $attributes));

    DB::table('subscriptions')->where('id', $subscription->id)->update(['confirmed_at' => null]);

    return $subscription;
}

function logStatusChange(Subscription $subscription, string $to, string $at): void
{
    DB::table('activity_log')->insert([
        'log_name' => 'default',
        'description' => 'updated',
        'event' => 'updated',
        'subject_type' => Subscription::class,
        'subject_id' => $subscription->id,
        'properties' => json_encode(['attributes' => ['status' => $to], 'old' => ['status' => 'pending']]),
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

it('backfills confirmed_at from the log entry that validated the affiliation', function (): void {
    $subscription = subscriptionWithoutConfirmedAt(['created_at' => '2026-08-01 09:00:00']);

    logStatusChange($subscription, 'confirmed', '2026-09-14 18:30:00');

    runBackfillSubscriptionConfirmedAtMigration();

    expect(DB::table('subscriptions')->find($subscription->id)->confirmed_at)
        ->toStartWith('2026-09-14 18:30:00');
});

it('falls back to the creation date when no transition was ever logged', function (): void {
    $subscription = subscriptionWithoutConfirmedAt(['created_at' => '2026-08-01 09:00:00']);

    runBackfillSubscriptionConfirmedAtMigration();

    expect(DB::table('subscriptions')->find($subscription->id)->confirmed_at)
        ->toStartWith('2026-08-01 09:00:00');
});

it('keeps the first validation when an affiliation was confirmed twice', function (): void {
    $subscription = subscriptionWithoutConfirmedAt(['created_at' => '2026-08-01 09:00:00']);

    logStatusChange($subscription, 'confirmed', '2026-09-14 18:30:00');
    logStatusChange($subscription, 'paid', '2026-11-02 10:00:00');

    runBackfillSubscriptionConfirmedAtMigration();

    expect(DB::table('subscriptions')->find($subscription->id)->confirmed_at)
        ->toStartWith('2026-09-14 18:30:00');
});

it('leaves affiliations that were never validated undated', function (string $status): void {
    $subscription = subscriptionWithoutConfirmedAt(['status' => $status]);

    runBackfillSubscriptionConfirmedAtMigration();

    expect(DB::table('subscriptions')->find($subscription->id)->confirmed_at)->toBeNull();
})->with(['pending', 'cancelled']);
