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

/**
 * Le cas A par le rapprochement en masse.
 *
 * Les transactions étaient indexées par `keyBy(référence)` : deux virements
 * portant la même communication structurée s'écrasaient l'un l'autre, et seul
 * le dernier survivait. Le premier disparaissait sans un mot — aucun test ne
 * pouvait le voir, celui qui s'en chargeait ayant recopié le `keyBy` fautif.
 *
 * La référence structurée est un identifiant que le club a lui-même émis :
 * elle désigne le paiement à elle seule. Le montant ne décide plus de *qui*,
 * seulement de *combien*.
 */
it('offers every transfer carrying the payment reference, not just the last one', function (): void {
    [$subscription, $payment] = affiliationAwaiting();

    foreach ([200.0, 165.0] as $amount) {
        Transaction::create([
            'date' => now()->toDateString(),
            'description' => 'VIREMENT EN VOTRE FAVEUR',
            'amount' => $amount,
            'counterparty_name' => $subscription->user->full_name,
            'structured_reference' => $payment->reference,
        ]);
    }

    $screen = reconcileScreen(User::factory()->create())
        ->call('previewBatchMatch');

    expect($screen->get('batchMatches'))->toHaveCount(2);

    $screen->call('confirmBatchReconcile');

    expect($payment->fresh()->amount_paid)->toBe(365.0)
        ->and($payment->fresh()->status)->toBe('paid')
        ->and($subscription->fresh()->balanceDue())->toBe(0.0);
})->group('payments', 'batch');

/**
 * Une transaction déjà entièrement affectée ne revient pas dans la sélection.
 *
 * Le masse filtrait sur `whereDoesntHave('payment')`, une colonne que plus
 * personne n'écrit depuis que le geste passe par l'action. Sans reprise, il
 * proposerait à l'infini des lignes qu'il a lui-même soldées — et I1 les
 * refuserait une à une, en silence, au milieu de la boucle.
 */
it('leaves out a transfer it has already allocated in full', function (): void {
    // Le paiement reste `pending` après le premier passage — il doit encore
    // 165 €. C'est ce qui rend ce test discriminant : filtrer sur le statut du
    // paiement ne suffit pas à écarter la transaction, il faut regarder ce
    // qu'elle a encore à placer.
    [, $payment] = affiliationAwaiting();

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 200.0,
        'counterparty_name' => 'Payeur',
        'structured_reference' => $payment->reference,
    ]);

    reconcileScreen(User::factory()->create())
        ->call('previewBatchMatch')
        ->call('confirmBatchReconcile');

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->amount_paid)->toBe(200.0)
        ->and($transaction->fresh()->isSettled())->toBeTrue();

    // Second passage : le paiement réclame toujours, la transaction n'a plus
    // rien à donner.
    expect(reconcileScreen(User::factory()->create())->call('previewBatchMatch')->get('batchMatches'))
        ->toBeEmpty();
})->group('payments', 'batch');

/**
 * Le remboursement passe par la même porte que l'encaissement.
 *
 * `confirmRefundReconcile()` écrivait `refund_transaction_id` et le statut à la
 * main. La colonne était `unique()` : un virement sortant ne pouvait payer
 * qu'un seul remboursement.
 */
it('executes a refund through the allocation action', function (): void {
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 365,
    ]);

    $refund = $subscription->payments()->create([
        'reference' => '999/0000/00065',
        'amount_due' => 65,
        'amount_paid' => 0,
        'status' => 'to_refund',
        'payment_method' => 'refund',
    ]);

    $outgoing = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN FAVEUR DE TIERS',
        'amount' => -65.0,
        'counterparty_name' => $member->full_name,
    ]);

    reconcileScreen(User::factory()->create())
        ->call('openRefundReconcile', $refund->id)
        ->set('selectedRefundTransactionId', $outgoing->id)
        ->call('confirmRefundReconcile');

    expect($refund->fresh()->status)->toBe('refunded')
        ->and($refund->fresh()->amount_paid)->toBe(65.0)
        ->and($outgoing->fresh()->allocated_amount)->toBe(-65.0)
        ->and($outgoing->fresh()->isSettled())->toBeTrue();
})->group('payments', 'reconciliation');

/**
 * Le trésorier ouvre un remboursement sur demande du membre.
 *
 * Aucun bouton n'existait : les six appelants de RequestSubscriptionRefundAction
 * sont des gestes de secrétariat — annuler une affiliation, arrêter un pack,
 * déplacer un membre. Un membre qui appelle pour dire « j'ai payé deux fois »
 * obligeait le trésorier à passer par le secrétaire, qui devait modifier une
 * affiliation pour provoquer un remboursement qu'il ne voulait pas provoquer.
 */
it('lets the treasurer open a refund on a payment, with a reason', function (): void {
    [$subscription, $payment] = affiliationAwaiting();

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 365.0,
        'counterparty_name' => $subscription->user->full_name,
    ]);

    reconcileScreen(User::factory()->create())
        // Les 365 € doivent être rentrés : on ne rembourse pas ce qu'on n'a pas.
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->call('confirmReconcile')
        ->call('openRefundRequest', $payment->id)
        ->set('refundRequestAmount', 65.0)
        ->set('refundRequestReason', 'Double virement du membre')
        ->call('confirmRefundRequest')
        ->assertHasNoErrors();

    $refund = $subscription->fresh()->payments()->where('payment_method', 'refund')->first();

    expect($refund)->not->toBeNull()
        ->and($refund->status)->toBe('to_refund')
        ->and($refund->amount_due)->toBe(65.0)
        ->and($refund->amount_paid)->toBe(0.0);
})->group('payments', 'refund');

/**
 * Le plafond est ce qui est réellement rentré, net des remboursements déjà
 * engagés. `netAmountPaid()` existait pour ça — sa docstring prévient que s'en
 * passer rembourserait deux fois — sans être appelée par aucun écran.
 */
it('refuses to refund more than the member actually paid', function (): void {
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
        ->call('confirmReconcile')
        ->call('openRefundRequest', $payment->id)
        ->set('refundRequestAmount', 300.0)
        ->set('refundRequestReason', 'Trop demandé')
        ->call('confirmRefundRequest');

    expect($subscription->fresh()->payments()->where('payment_method', 'refund')->count())->toBe(0);
})->group('payments', 'refund');

it('requires a reason before opening a refund', function (): void {
    [$subscription, $payment] = affiliationAwaiting();

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 365.0,
        'counterparty_name' => $subscription->user->full_name,
    ]);

    reconcileScreen(User::factory()->create())
        ->call('openReconcile', $payment->id)
        ->set('selectedTransactionId', $transaction->id)
        ->call('confirmReconcile')
        ->call('openRefundRequest', $payment->id)
        ->set('refundRequestAmount', 65.0)
        ->set('refundRequestReason', '   ')
        ->call('confirmRefundRequest');

    expect($subscription->fresh()->payments()->where('payment_method', 'refund')->count())->toBe(0);
})->group('payments', 'refund');
