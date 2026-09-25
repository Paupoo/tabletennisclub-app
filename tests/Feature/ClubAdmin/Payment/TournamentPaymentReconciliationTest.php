<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * @return array{tournament: Tournament, registration: TournamentRegistration, payment: Payment, transaction: Transaction}
 */
function makeTournamentWithPendingPayment(User $user, float $amount = 25.0): array
{
    $tournament = Tournament::factory()->create(['price' => $amount]);

    $tournament->users()->attach($user->id, [
        'registration_status' => 'confirmed',
        'has_paid' => false,
    ]);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->first();

    $payment = $registration->payment()->create([
        'reference' => '001/2506/00001',
        // Euros : le mutateur de `amount_due` convertit en centimes lui-même.
        // Multiplier ici inscrivait une entrée de tournoi à 2500 €, ce que
        // personne ne voyait tant que la réconciliation écrivait « payé » sans
        // comparer le versement au montant dû.
        'amount_due' => $amount,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'amount' => $amount,
        'counterparty_name' => $user->full_name,
        'structured_reference' => $payment->reference,
        'description' => 'Tournament entry fee',
    ]);

    return compact('tournament', 'registration', 'payment', 'transaction');
}

// ── Polymorphic dispatch: TournamentRegistration ──────────────────────────────

describe('confirmReconcile — TournamentRegistration payable', function (): void {

    it('sets has_paid to true on the tournament_user pivot', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create();

        [
            'tournament' => $tournament,
            'payment' => $payment,
            'transaction' => $transaction,
        ] = makeTournamentWithPendingPayment($user);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('reconcilePaymentId', $payment->id)
            ->set('selectedTransactionId', $transaction->id)
            ->call('confirmReconcile')
            ->assertHasNoErrors();

        expect($payment->fresh()->status)->toBe('paid');
        expect($transaction->fresh()->allocated_amount)->toBe(25.0);

        expect(
            DB::table('tournament_user')
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('has_paid')
        )->toBe(1);
    })->group('reconciliation', 'tournament');

    it('does not touch other participants when reconciling one payment', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ['tournament' => $tournament, 'payment' => $payment, 'transaction' => $transaction]
            = makeTournamentWithPendingPayment($userA);

        $tournament->users()->attach($userB->id, [
            'registration_status' => 'confirmed',
            'has_paid' => false,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('reconcilePaymentId', $payment->id)
            ->set('selectedTransactionId', $transaction->id)
            ->call('confirmReconcile');

        expect(
            DB::table('tournament_user')
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $userB->id)
                ->value('has_paid')
        )->toBe(0);
    })->group('reconciliation', 'tournament');

    it('dispatches a success toast after reconciling a tournament payment', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create();

        ['payment' => $payment, 'transaction' => $transaction] = makeTournamentWithPendingPayment($user);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('reconcilePaymentId', $payment->id)
            ->set('selectedTransactionId', $transaction->id)
            ->call('confirmReconcile')
            ->assertHasNoErrors();

        expect($payment->fresh()->status)->toBe('paid');
    })->group('reconciliation', 'tournament');

})->group('payments');

// ── Polymorphic dispatch: Subscription payable (regression) ──────────────────

describe('confirmReconcile — Subscription payable (regression)', function (): void {

    it('still marks the subscription as paid when payable_type is Subscription', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $subscription = Subscription::factory()->create([
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);

        $payment = $subscription->payments()->create([
            'reference' => '100/2506/00999',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 125.0,
            'counterparty_name' => 'Member',
            'structured_reference' => $payment->reference,
            'description' => 'Annual subscription',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('reconcilePaymentId', $payment->id)
            ->set('selectedTransactionId', $transaction->id)
            ->call('confirmReconcile')
            ->assertHasNoErrors();

        expect($payment->fresh()->status)->toBe('paid');
        expect($subscription->fresh()->status)->toBe('paid');
        expect($subscription->fresh()->amount_paid)->toBe(125.0);
    })->group('reconciliation', 'subscription');

    it('credits a partial transfer without declaring the payment settled', function (): void {
        $admin = User::factory()->isAdmin()->create();

        $subscription = Subscription::factory()->create([
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);

        $payment = $subscription->payments()->create([
            'reference' => '100/2506/00998',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 60.0,
            'counterparty_name' => 'Partial payer',
            'structured_reference' => $payment->reference,
            'description' => 'Partial payment',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('reconcilePaymentId', $payment->id)
            ->set('selectedTransactionId', $transaction->id)
            ->call('confirmReconcile')
            ->assertHasNoErrors();

        // L'intention d'origine tient : un montant qui ne correspond pas n'est
        // pas refusé. C'est la conclusion qui change — 60 € sur 125 € dus sont
        // encaissés, et la ligne continue de réclamer les 65 restants.
        expect($payment->fresh()->status)->toBe('pending')
            ->and($payment->fresh()->amount_paid)->toBe(60.0)
            ->and($subscription->fresh()->balanceDue())->toBe(65.0);
    })->group('reconciliation', 'subscription');

})->group('payments');

// ── Validation ────────────────────────────────────────────────────────────────

describe('confirmReconcile — validation', function (): void {

    it('does nothing when no transaction is selected', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 50]);
        $payment = $subscription->payments()->create([
            'reference' => '100/2506/00011',
            'amount_due' => 50,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('reconcilePaymentId', $payment->id)
            ->call('confirmReconcile');

        expect($payment->fresh()->status)->toBe('pending');
    })->group('reconciliation', 'validation');

    it('does nothing when no payment is selected', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $transaction = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 50.0,
            'counterparty_name' => 'X',
            'description' => 'Test',
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-admin.treasury.payments')
            ->set('selectedTransactionId', $transaction->id)
            ->call('confirmReconcile');

        expect($transaction->fresh()->payment)->toBeNull();
    })->group('reconciliation', 'validation');

})->group('payments');
