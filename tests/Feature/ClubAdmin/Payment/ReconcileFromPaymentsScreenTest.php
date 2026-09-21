<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/**
 * Le rapprochement tel que le trésorier le fait réellement.
 *
 * `ReconciliationTest` couvrait ce geste en exécutant lui-même l'`update()`
 * qu'il vérifiait : l'assertion recalculait l'attendu comme le code, donc elle
 * ne pouvait jamais le contredire. `confirmReconcile()` n'y était jamais
 * appelée, et c'est pourquoi « 50 € soldent une cotisation de 365 € » a pu
 * vivre en production sous une suite verte.
 */
function reconcileScreen(User $actor)
{
    $actor->assignRole(Role::TREASURY->value);

    return Livewire::actingAs($actor)->test('pages::club-admin.treasury.payments');
}

/** @return array{0: Subscription, 1: Payment} */
function affiliationAwaiting(float $due = 365.0): array
{
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => $due,
    ]);

    return [$subscription, $subscription->payments()->create([
        'reference' => '123/4567/89012',
        'amount_due' => $due,
        'amount_paid' => 0,
        'status' => 'pending',
    ])];
}

it('leaves the balance owed when the treasurer reconciles a partial transfer', function (): void {
    [$subscription, $payment] = affiliationAwaiting();

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 200.0,
        'counterparty_name' => $subscription->user->full_name,
    ]);

    reconcileScreen(User::factory()->create())
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->call('confirmReconcile');

    expect($payment->fresh()->amount_paid)->toBe(200.0)
        ->and($payment->fresh()->status)->toBe('pending')
        ->and($subscription->fresh()->status)->toBe('confirmed')
        ->and($subscription->fresh()->balanceDue())->toBe(165.0)
        // Le geste passe bien par l'action : sans ligne de crédit, le miroir
        // de la transaction resterait à zéro.
        ->and($transaction->fresh()->allocated_amount)->toBe(200.0);
})->group('payments', 'reconciliation');
