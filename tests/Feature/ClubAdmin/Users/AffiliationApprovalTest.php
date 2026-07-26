<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Subscriptions\Notifications\SubscriptionFormulaChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

pest()->group('club-admin', 'registrations');

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    $this->season = Season::factory()->create(['is_active' => true, 'affiliations_open' => true]);
    actingAs(User::factory()->isAdmin()->create());
});

it('refuses to approve an affiliation when the member has no licence number', function (): void {
    $member = User::factory()->create(['licence' => null, 'ranking' => 'C4']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
        'is_competitive' => true,
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('currentRequestId', $subscription->id)
        ->call('approve');

    expect($subscription->fresh()->status)->toBe('pending')
        ->and($subscription->payments()->count())->toBe(0);
});

it('accepts the affiliation with the licence number entered in the review modal', function (): void {
    $member = User::factory()->create(['licence' => null, 'ranking' => 'NA']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
        'is_competitive' => true,
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->set('reviewLicence', '123456')
        ->set('reviewRanking', 'C4')
        ->call('approve');

    expect($member->fresh()->licence)->toBe('123456')
        ->and($member->fresh()->ranking)->toBe('C4')
        ->and($subscription->fresh()->status)->toBe('confirmed')
        ->and($subscription->payments()->count())->toBe(1);
});

it('switches the formula of a pending affiliation and reprices it, silently', function (): void {
    Notification::fake();

    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'D6']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
        'is_competitive' => false,
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->call('changeFormula');

    expect($subscription->fresh()->is_competitive)->toBeTrue()
        ->and((float) $subscription->fresh()->subscription_price)->toBe(125.0);

    Notification::assertNothingSent();
});

it('bills the complement and notifies when a paid affiliation moves to competition', function (): void {
    Notification::fake();

    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'D6']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
        'is_competitive' => false,
        'subscription_price' => 60,
        'amount_due' => 60,
    ]);
    $subscription->payments()->create([
        'reference' => '+++000/0000/00097+++',
        'amount_due' => 60,
        'amount_paid' => 60,
        'status' => 'paid',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->call('changeFormula');

    $complement = $subscription->payments()->where('status', 'pending')->first();

    expect($subscription->fresh()->is_competitive)->toBeTrue()
        ->and($complement)->not->toBeNull()
        ->and((float) $complement->amount_due)->toBe(65.0);

    Notification::assertSentTo($member, SubscriptionFormulaChangedNotification::class);
});

it('caps the refund at what the member actually paid when leaving the competition', function (): void {
    Notification::fake();

    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'D6']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
        'is_competitive' => true,
        'subscription_price' => 125,
        'amount_due' => 125,
    ]);
    // Only 40 € ever came in, so the club can never owe the full 65 € back.
    $subscription->payments()->create([
        'reference' => '+++000/0000/00097+++',
        'amount_due' => 125,
        'amount_paid' => 40,
        'status' => 'paid',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->call('changeFormula');

    expect($subscription->fresh()->is_competitive)->toBeFalse()
        ->and($subscription->payments()->where('status', 'pending')->count())->toBe(0);

    Notification::assertSentTo(
        $member,
        SubscriptionFormulaChangedNotification::class,
        fn (SubscriptionFormulaChangedNotification $notification): bool => $notification->delta === -40.0,
    );
});

it('refuses to leave the competition while the player is still in an interclub team', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'D6']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'paid',
        'is_competitive' => true,
    ]);
    $team = Team::factory()->create(['season_id' => $this->season->id]);
    $team->users()->attach($member);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->call('changeFormula');

    expect($subscription->fresh()->is_competitive)->toBeTrue();
});

it('rebuilds the force list when a member joins the competition', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'D6']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'confirmed',
        'is_competitive' => false,
    ]);

    expect($member->fresh()->force_list)->toBeNull();

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->call('changeFormula');

    expect($member->fresh()->force_list)->not->toBeNull();
});

it('refuses a licence number that is not exactly six digits', function (string $licence): void {
    $member = User::factory()->create(['licence' => null, 'ranking' => 'C4']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->set('reviewLicence', $licence)
        ->call('approve');

    expect($subscription->fresh()->status)->toBe('pending')
        ->and($member->fresh()->licence)->toBeNull();
})->with([
    'too short' => '12345',
    'too long' => '1234567',
    'not numeric' => 'ABC123',
]);

it('refuses a licence number already held by another member', function (): void {
    User::factory()->create(['licence' => '123456']);

    $member = User::factory()->create(['licence' => null, 'ranking' => 'C4']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->call('review', $subscription->id)
        ->set('reviewLicence', '123456')
        ->call('approve');

    expect($subscription->fresh()->status)->toBe('pending')
        ->and($member->fresh()->licence)->toBeNull();
});

it('refuses to approve an affiliation when the member has no ranking', function (): void {
    $member = User::factory()->create(['licence' => '123456', 'ranking' => 'NA']);
    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'season_id' => $this->season->id,
        'status' => 'pending',
        'is_competitive' => false,
    ]);

    Livewire::test('pages::club-admin.users.registrations')
        ->set('currentRequestId', $subscription->id)
        ->call('approve');

    expect($subscription->fresh()->status)->toBe('pending')
        ->and($subscription->payments()->count())->toBe(0);
});
