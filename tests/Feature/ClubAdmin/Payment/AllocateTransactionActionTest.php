<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * Le membre verse une partie de sa cotisation et rien d'autre.
 *
 * C'est le cas que la réconciliation actuelle ne sait pas dire : elle écrit
 * `status = 'paid'` sans condition, et une affiliation de 365 € se retrouve
 * soldée par un virement de 200 €.
 */
it('leaves the balance owed when a transfer covers only part of the affiliation', function (): void {
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 365,
    ]);

    $payment = $subscription->payments()->create([
        'reference' => '123/4567/89012',
        'amount_due' => 365,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 200.0,
        'counterparty_name' => $member->full_name,
        'structured_reference' => $payment->reference,
    ]);

    (new AllocateTransactionAction)($transaction, [$payment->id => 200.0]);

    expect($payment->fresh()->amount_paid)->toBe(200.0)
        ->and($payment->fresh()->status)->toBe('pending')
        ->and($subscription->fresh()->balanceDue())->toBe(165.0)
        ->and($subscription->fresh()->status)->toBe('confirmed');
})->group('payments', 'reconciliation');
