<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Services\AttestationEligibility;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Shared\Enums\AttestationRefusal;

/**
 * An affiliation as the office leaves it once everything is in order.
 *
 * @param  array<string, mixed>  $attributes
 */
function affiliationInOrder(Season $season, array $attributes = []): Subscription
{
    $subscription = Subscription::factory()->for(User::factory())->create(array_merge([
        'season_id' => $season->id,
        'status' => 'paid',
        'amount_due' => 125,
    ], $attributes));

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
        'amount_due' => $subscription->amount_due,
        'amount_paid' => $subscription->amount_due,
        'status' => 'paid',
    ]);

    return $subscription->refresh();
}

it('lets a validated member who owes nothing ask for an attestation', function (): void {
    $season = makeActiveSeason();
    $subscription = affiliationInOrder($season);

    $verdict = app(AttestationEligibility::class)->for($subscription->user, $season);

    expect($verdict->allowed)->toBeTrue()
        ->and($verdict->refusal)->toBeNull();
});

it('refuses a member with no affiliation at all for the season', function (): void {
    $season = makeActiveSeason();
    $member = User::factory()->create();

    $verdict = app(AttestationEligibility::class)->for($member, $season);

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->refusal)->toBe(AttestationRefusal::NoAffiliation);
});

it('refuses an affiliation the committee has not validated yet', function (string $status): void {
    $season = makeActiveSeason();
    $subscription = affiliationInOrder($season, ['status' => $status]);

    $verdict = app(AttestationEligibility::class)->for($subscription->user, $season);

    expect($verdict->refusal)->toBe(AttestationRefusal::NoAffiliation);
})->with(['pending', 'cancelled']);

it('refuses an affiliation that is not settled in full', function (): void {
    $season = makeActiveSeason();
    $subscription = Subscription::factory()->for(User::factory())->create([
        'season_id' => $season->id,
        'status' => 'confirmed',
        'amount_due' => 125,
    ]);

    Payment::factory()->create([
        'payable_type' => Subscription::class,
        'payable_id' => $subscription->id,
        'amount_due' => 125,
        'amount_paid' => 60,
        'status' => 'paid',
    ]);

    $verdict = app(AttestationEligibility::class)->for($subscription->user, $season);

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->refusal)->toBe(AttestationRefusal::BalanceDue);
});

it('ignores debts that are not the affiliation', function (): void {
    $season = makeActiveSeason();
    $subscription = affiliationInOrder($season);

    $fine = Fine::factory()->create(['user_id' => $subscription->user_id]);
    Payment::factory()->create([
        'payable_type' => Fine::class,
        'payable_id' => $fine->id,
        'amount_due' => 15,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $verdict = app(AttestationEligibility::class)->for($subscription->user, $season);

    expect($verdict->allowed)->toBeTrue();
});

it('does not count an affiliation held for another season', function (): void {
    $season = makeActiveSeason();
    $lastSeason = Season::factory()->create(['is_active' => false]);
    $subscription = affiliationInOrder($lastSeason);

    $verdict = app(AttestationEligibility::class)->for($subscription->user, $season);

    expect($verdict->refusal)->toBe(AttestationRefusal::NoAffiliation);
});
