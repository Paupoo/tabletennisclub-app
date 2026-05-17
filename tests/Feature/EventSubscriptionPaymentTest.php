<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentRegistration;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createTournamentPayment(User $user, Tournament $tournament, string $status = 'pending')
{
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->first();

    return $registration->payment()->create([
        'reference' => '001/2026/00001',
        'amount_due' => 10,
        'amount_paid' => 0,
        'status' => $status,
    ]);
}

function mountEventSubscription(User $user)
{
    return Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $user]);
}

// ── pendingPayments ───────────────────────────────────────────────────────────

describe('pendingPayments', function () {
    it('returns the user pending tournament payment', function () {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        createTournamentPayment($user, $tournament);

        $component = mountEventSubscription($user);

        expect($component->get('pendingPayments'))->toHaveCount(1);
    });

    it('excludes pending payments belonging to another user', function () {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tournament = paymentTournament();
        createTournamentPayment($otherUser, $tournament);

        $component = mountEventSubscription($user);

        expect($component->get('pendingPayments'))->toHaveCount(0);
    });

    it('excludes paid payments', function () {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        createTournamentPayment($user, $tournament, 'paid');

        $component = mountEventSubscription($user);

        expect($component->get('pendingPayments'))->toHaveCount(0);
    });
});

// ── openPaymentModal ──────────────────────────────────────────────────────────

describe('openPaymentModal', function () {
    it('opens the modal and stores the payment id', function () {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        $payment = createTournamentPayment($user, $tournament);

        mountEventSubscription($user)
            ->call('openPaymentModal', $payment->id)
            ->assertSet('paymentModal', true)
            ->assertSet('selectedPaymentId', $payment->id);
    });

    it('generates a non-empty QR code string', function () {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        $payment = createTournamentPayment($user, $tournament);

        $component = mountEventSubscription($user)
            ->call('openPaymentModal', $payment->id);

        expect($component->get('paymentQr'))->toStartWith('data:image/png;base64,');
    });

    it('renders the tournament name in the modal', function () {
        $user = User::factory()->create();
        $tournament = paymentTournament(['name' => 'Summer Cup 2026']);
        $payment = createTournamentPayment($user, $tournament);

        mountEventSubscription($user)
            ->call('openPaymentModal', $payment->id)
            ->assertSee('Summer Cup 2026');
    });
});
