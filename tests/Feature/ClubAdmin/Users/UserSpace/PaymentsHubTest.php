<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use Livewire\Livewire;

const PAYMENTS_HUB_COMPONENT = 'pages::club-admin.users.user-space.payments';

function subscriptionPayment(User $user, Season $season, string $status = 'pending', float $amount = 50): Payment
{
    $subscription = Subscription::factory()->for($user)->create([
        'season_id' => $season->id,
        'status' => $status === 'paid' ? 'paid' : 'confirmed',
    ]);

    return $subscription->payments()->create([
        'reference' => '001/2026/' . fake()->unique()->numberBetween(10000, 99999),
        'amount_due' => $amount,
        'amount_paid' => $status === 'paid' ? $amount : 0,
        'status' => $status,
    ]);
}

beforeEach(function (): void {
    $this->season = makeActiveSeason();
});

it('shows the members own pending and paid payments', function (): void {
    $user = User::factory()->create();
    subscriptionPayment($user, $this->season, 'pending', 40);
    subscriptionPayment($user, $this->season, 'paid', 60);

    Livewire::actingAs($user)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $user])
        ->assertOk()
        ->assertSee('40,00')
        ->assertSee('60,00')
        ->assertSee(__('Subscription'));
});

it('never shows another members payment', function (): void {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    subscriptionPayment($stranger, $this->season, 'pending', 123);

    Livewire::actingAs($user)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $user])
        ->assertDontSee('123,00');
});

it('shows a wards payment to their guardian', function (): void {
    $parent = User::factory()->create();
    $child = User::factory()->create(['first_name' => 'Kiddo', 'last_name' => 'Junior']);

    $guardian = Guardian::factory()->create(['user_id' => $parent->id]);
    $guardian->users()->attach($child->id);

    subscriptionPayment($child, $this->season, 'pending', 77);

    Livewire::actingAs($parent)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $parent])
        ->assertSee('77,00')
        ->assertSee('Kiddo');
});

it('does not show payments of a user the member does not guard', function (): void {
    $parent = User::factory()->create();
    $unrelatedChild = User::factory()->create();

    // A guardian record exists but for someone else entirely.
    $otherGuardian = Guardian::factory()->create(['user_id' => User::factory()->create()->id]);
    $otherGuardian->users()->attach($unrelatedChild->id);

    subscriptionPayment($unrelatedChild, $this->season, 'pending', 999);

    Livewire::actingAs($parent)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $parent])
        ->assertDontSee('999,00');
});

it('opens the QR modal for an in-scope pending payment', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create();
    $payment = subscriptionPayment($user, $this->season, 'pending', 30);

    $component = Livewire::actingAs($user)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $user])
        ->call('openPaymentModal', $payment->id)
        ->assertSet('paymentModal', true)
        ->assertSet('selectedPaymentId', $payment->id);

    expect($component->get('paymentQr'))->toStartWith('data:image/png;base64,');
});

it('refuses to open a payment outside the members scope', function (): void {
    Club::factory()->ownClub()->create();
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $payment = subscriptionPayment($stranger, $this->season, 'pending', 30);

    Livewire::actingAs($user)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $user])
        ->call('openPaymentModal', $payment->id)
        ->assertForbidden();
});

it('is self-only', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Livewire::actingAs($user)
        ->test(PAYMENTS_HUB_COMPONENT, ['user' => $other])
        ->assertForbidden();
});
