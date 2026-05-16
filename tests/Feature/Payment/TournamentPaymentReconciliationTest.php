<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Payment\Transaction;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentRegistration;
use Illuminate\Support\Facades\DB;

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
        'amount_due' => (int) ($amount * 100),
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

describe('reconcileStore — TournamentRegistration payable', function () {

    it('sets has_paid to true on the tournament_user pivot', function () {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create();

        [
            'tournament' => $tournament,
            'payment' => $payment,
            'transaction' => $transaction,
        ] = makeTournamentWithPendingPayment($user);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.transactions.reconcile'));

        expect($payment->fresh()->status)->toBe('paid');
        expect($payment->fresh()->transaction_id)->toEqual($transaction->id);

        expect(
            DB::table('tournament_user')
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('has_paid')
        )->toBe(1);
    })->group('reconciliation', 'tournament');

    it('does not touch other participants when reconciling one payment', function () {
        $admin = User::factory()->isAdmin()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        ['tournament' => $tournament, 'payment' => $payment, 'transaction' => $transaction]
            = makeTournamentWithPendingPayment($userA);

        $tournament->users()->attach($userB->id, [
            'registration_status' => 'confirmed',
            'has_paid' => false,
        ]);

        $this->actingAs($admin)->post(route('admin.transactions.reconcile.store'), [
            'transaction_id' => $transaction->id,
            'payment_id' => $payment->id,
        ]);

        expect(
            DB::table('tournament_user')
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $userB->id)
                ->value('has_paid')
        )->toBe(0);
    })->group('reconciliation', 'tournament');

    it('returns a success flash after reconciling a tournament payment', function () {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create();

        ['payment' => $payment, 'transaction' => $transaction] = makeTournamentWithPendingPayment($user);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id,
            ])
            ->assertSessionHas('success');
    })->group('reconciliation', 'tournament');

})->group('payments');

// ── Polymorphic dispatch: Subscription payable (regression) ──────────────────

describe('reconcileStore — Subscription payable (regression)', function () {

    it('still marks the subscription as paid when payable_type is Subscription', function () {
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

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.transactions.reconcile'));

        expect($payment->fresh()->status)->toBe('paid');
        expect($subscription->fresh()->status)->toBe('paid');
        expect($subscription->fresh()->amount_paid)->toBe(125.0);
    })->group('reconciliation', 'subscription');

    it('reconciles a subscription payment regardless of amount (partial payment)', function () {
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

        // Transaction with a different amount (partial payment)
        $transaction = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 60.0,
            'counterparty_name' => 'Partial payer',
            'structured_reference' => $payment->reference,
            'description' => 'Partial payment',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), [
                'transaction_id' => $transaction->id,
                'payment_id' => $payment->id,
            ])
            ->assertRedirect(route('admin.transactions.reconcile'));

        expect($payment->fresh()->status)->toBe('paid');
    })->group('reconciliation', 'subscription');

})->group('payments');

// ── Validation ────────────────────────────────────────────────────────────────

describe('reconcileStore — validation', function () {

    it('rejects missing transaction_id', function () {
        $admin = User::factory()->isAdmin()->create();
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 50]);
        $payment = $subscription->payments()->create([
            'reference' => '100/2506/00011',
            'amount_due' => 50,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), ['payment_id' => $payment->id])
            ->assertSessionHasErrors('transaction_id');
    })->group('reconciliation', 'validation');

    it('rejects non-existent transaction_id', function () {
        $admin = User::factory()->isAdmin()->create();
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 50]);
        $payment = $subscription->payments()->create([
            'reference' => '100/2506/00012',
            'amount_due' => 50,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), [
                'transaction_id' => 999999,
                'payment_id' => $payment->id,
            ])
            ->assertSessionHasErrors('transaction_id');
    })->group('reconciliation', 'validation');

    it('rejects missing payment_id', function () {
        $admin = User::factory()->isAdmin()->create();
        $transaction = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 50.0,
            'counterparty_name' => 'X',
            'description' => 'Test',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reconcile.store'), ['transaction_id' => $transaction->id])
            ->assertSessionHasErrors('payment_id');
    })->group('reconciliation', 'validation');

})->group('payments');
