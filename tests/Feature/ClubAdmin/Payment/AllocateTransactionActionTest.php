<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
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

/**
 * Une mère vire 730 € pour ses deux enfants.
 *
 * `payments.transaction_id` était `UNIQUE` : rattacher le virement à Léa
 * interdisait de le rattacher à Tom. Le trésorier n'avait d'autre choix que de
 * marquer le second payé sans transaction, ou de saisir le virement deux fois.
 */
it('splits one transfer across the two children of the same family', function (): void {
    $mother = User::factory()->create();

    $lea = User::factory()->create();
    $tom = User::factory()->create();

    $payments = collect([$lea, $tom])->map(function (User $child): object {
        $subscription = Subscription::factory()->create([
            'user_id' => $child->id,
            'status' => 'confirmed',
            'amount_due' => 365,
        ]);

        return (object) [
            'subscription' => $subscription,
            'payment' => $subscription->payments()->create([
                'reference' => sprintf('%03d/4567/89012', $child->id),
                'amount_due' => 365,
                'amount_paid' => 0,
                'status' => 'pending',
            ]),
        ];
    });

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 730.0,
        'counterparty_name' => $mother->full_name,
    ]);

    (new AllocateTransactionAction)($transaction, [
        $payments[0]->payment->id => 365.0,
        $payments[1]->payment->id => 365.0,
    ]);

    expect($payments[0]->subscription->fresh()->balanceDue())->toBe(0.0)
        ->and($payments[1]->subscription->fresh()->balanceDue())->toBe(0.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(730.0);
})->group('payments', 'reconciliation');

/**
 * I1 : on n'affecte pas plus que ce que la banque a bougé.
 *
 * Aucun cas de gestion ne le justifie — au-delà, on invente de l'argent. Et le
 * refus doit être entier : une ventilation à moitié écrite laisserait les deux
 * miroirs en désaccord avec le relevé.
 */
it('refuses to allocate more than the bank actually moved', function (): void {
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
    ]);

    expect(fn (): mixed => (new AllocateTransactionAction)($transaction, [$payment->id => 250.0]))
        ->toThrow(DomainException::class);

    expect($payment->fresh()->amount_paid)->toBe(0.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(0.0);
})->group('payments', 'reconciliation');

/**
 * I1 porte sur le total, pas sur le dernier geste.
 *
 * Une ligne de relevé se ventile en plusieurs fois — le trésorier affecte ce
 * qu'il reconnaît aujourd'hui et laisse le reste. Sans cumul, chaque nouvelle
 * ventilation repartirait du montant plein et la ligne pourrait être affectée
 * deux fois.
 */
it('counts the allocations already posted when it checks the transaction ceiling', function (): void {
    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => 365,
    ]);

    $first = $subscription->payments()->create([
        'reference' => '123/4567/89012',
        'amount_due' => 365,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $second = $subscription->payments()->create([
        'reference' => '123/4567/89013',
        'amount_due' => 100,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 200.0,
        'counterparty_name' => $member->full_name,
    ]);

    (new AllocateTransactionAction)($transaction, [$first->id => 150.0]);

    expect(fn (): mixed => (new AllocateTransactionAction)($transaction, [$second->id => 100.0]))
        ->toThrow(DomainException::class);

    expect($first->fresh()->amount_paid)->toBe(150.0)
        ->and($second->fresh()->amount_paid)->toBe(0.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(150.0);
})->group('payments', 'reconciliation');

/**
 * Le versement qui solde vraiment porte l'affiliation à `paid`.
 *
 * Le pendant du premier test : `markAsPaid()` n'était appelé sous aucune
 * condition, et c'est l'autre moitié de la correction — une fois le solde
 * atteint, il doit bien être appelé.
 */
it('marks the affiliation paid once the balance reaches zero', function (): void {
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
        'amount' => 365.0,
        'counterparty_name' => $member->full_name,
    ]);

    (new AllocateTransactionAction)($transaction, [$payment->id => 365.0]);

    expect($subscription->fresh()->status)->toBe('paid')
        ->and($subscription->fresh()->balanceDue())->toBe(0.0)
        // La colonne de l'affiliation suit, au lieu d'être écrasée par le
        // dernier virement venu.
        ->and($subscription->fresh()->amount_paid)->toBe(365.0);
})->group('payments', 'reconciliation');

/**
 * Le club rembourse deux membres d'un seul virement sortant.
 *
 * `payments.refund_transaction_id` était `UNIQUE` lui aussi : le trésorier
 * devait faire deux virements, ou laisser un des deux remboursements non
 * rapproché.
 *
 * Le montant d'un crédit reste positif — le sens de l'argent est porté par la
 * transaction, comme `netAmountPaid()` le pose pour `payment_method`. C'est le
 * miroir qui prend le signe, sans quoi une ligne de débit entièrement traitée
 * ne pourrait jamais se dire soldée.
 */
it('pays two refunds with a single outgoing transfer', function (): void {
    $refunds = collect([65.0, 40.0])->map(function (float $amount): object {
        $subscription = Subscription::factory()->create([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'amount_due' => 365,
        ]);

        return $subscription->payments()->create([
            'reference' => sprintf('999/0000/%05d', (int) ($amount * 100)),
            'amount_due' => $amount,
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);
    });

    $transaction = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN FAVEUR DE TIERS',
        'amount' => -105.0,
        'counterparty_name' => 'MARTIN Sophie',
    ]);

    (new AllocateTransactionAction)($transaction, [
        $refunds[0]->id => 65.0,
        $refunds[1]->id => 40.0,
    ]);

    expect($refunds[0]->fresh()->amount_paid)->toBe(65.0)
        ->and($refunds[1]->fresh()->amount_paid)->toBe(40.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(-105.0);
})->group('payments', 'reconciliation');

/**
 * Le cas A de bout en bout : Paul paie en deux fois.
 *
 * Tant que le solde n'est pas atteint la ligne reste `pending`, et c'est ce qui
 * laisse `SendPaymentReminderJob` continuer de relancer — il teste
 * `status !== 'pending'` et sortirait sur n'importe quel statut intermédiaire.
 * Au solde, elle bascule : c'est l'autre moitié de la règle.
 */
it('turns a payment line paid only once it is fully credited', function (): void {
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

    $september = Transaction::create([
        'date' => '2026-09-05',
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 200.0,
        'counterparty_name' => $member->full_name,
        'structured_reference' => $payment->reference,
    ]);

    (new AllocateTransactionAction)($september, [$payment->id => 200.0]);

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->amount_paid)->toBe(200.0);

    $november = Transaction::create([
        'date' => '2026-11-03',
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 165.0,
        'counterparty_name' => $member->full_name,
        'structured_reference' => $payment->reference,
    ]);

    (new AllocateTransactionAction)($november, [$payment->id => 165.0]);

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->amount_paid)->toBe(365.0)
        ->and($subscription->fresh()->status)->toBe('paid');
})->group('payments', 'reconciliation');

/**
 * Un remboursement promis n'est pas un remboursement versé.
 *
 * `RequestSubscriptionRefundAction` écrivait `amount_paid` dès la création,
 * avant qu'un euro n'ait quitté la banque : les deux états portaient les mêmes
 * chiffres et seul le statut les distinguait. Avec le miroir, `amount_due` dit
 * l'engagement et `amount_paid` ce qui est sorti.
 *
 * `netAmountPaid()` ne bouge pas entre les deux : une demande dans le circuit
 * compte déjà comme sortie — sa docstring le pose, la rejouer créerait un
 * doublon. Elle lit simplement la colonne qui ne ment plus.
 */
it('tells a promised refund from an executed one', function (): void {
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

    $incoming = Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EN VOTRE FAVEUR',
        'amount' => 365.0,
        'counterparty_name' => $member->full_name,
    ]);

    (new AllocateTransactionAction)($incoming, [$payment->id => 365.0]);

    $refund = (new RequestSubscriptionRefundAction)($subscription, 65.0);

    // Promis : engagé, rien de sorti.
    expect($refund->fresh()->amount_due)->toBe(65.0)
        ->and($refund->fresh()->amount_paid)->toBe(0.0)
        ->and($refund->fresh()->status)->toBe('to_refund')
        ->and($subscription->fresh()->netAmountPaid())->toBe(300.0);

    $outgoing = Transaction::create([
        'date' => now()->addDays(3)->toDateString(),
        'description' => 'VIREMENT EN FAVEUR DE TIERS',
        'amount' => -65.0,
        'counterparty_name' => $member->full_name,
    ]);

    (new AllocateTransactionAction)($outgoing, [$refund->id => 65.0]);

    // Exécuté : la banque a bougé, le net du membre est inchangé.
    expect($refund->fresh()->amount_paid)->toBe(65.0)
        ->and($refund->fresh()->status)->toBe('refunded')
        ->and($subscription->fresh()->netAmountPaid())->toBe(300.0);
})->group('payments', 'reconciliation');
