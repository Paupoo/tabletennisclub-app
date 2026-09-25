<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

/**
 * Le cas C : Paul arrondit son virement à 370 € pour une cotisation de 365 €.
 *
 * Les 5 € restent sur la transaction, disponibles — c'est tout l'intérêt de
 * loger l'excédent là plutôt que sur le paiement. Mais si personne ne les
 * réclame, la ligne resterait « partiellement affectée » à vie et polluerait la
 * liste du trésorier jusqu'à ce qu'il cesse de s'en servir.
 */
function residueFixture(float $transferred): Transaction
{
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
        'amount' => $transferred,
        'counterparty_name' => $member->full_name,
    ]);

    (new AllocateTransactionAction)($transaction, [$payment->id => 365.0]);

    return $transaction->fresh();
}

it('closes a residue nobody will claim', function (): void {
    $transaction = residueFixture(370.0);

    expect($transaction->isSettled())->toBeFalse()
        ->and($transaction->residue())->toBe(5.0);

    (new SettleTransactionResidueAction)($transaction, 'Arrondi du membre, acquis au club');

    expect($transaction->fresh()->isSettled())->toBeTrue()
        ->and($transaction->fresh()->settled_reason)->toBe('Arrondi du membre, acquis au club');
})->group('payments', 'reconciliation');

/**
 * Le motif n'est pas décoratif.
 *
 * `ReconcileTrainingPackAction` l'exige déjà dès qu'un montant est forcé, pour
 * la même raison : une somme que le club garde sans pouvoir dire pourquoi n'est
 * pas défendable devant le membre qui l'a versée.
 */
it('refuses to write off a residue without a reason', function (): void {
    $transaction = residueFixture(370.0);

    expect(fn (): mixed => (new SettleTransactionResidueAction)($transaction, '   '))
        ->toThrow(DomainException::class);

    expect($transaction->fresh()->isSettled())->toBeFalse()
        ->and($transaction->fresh()->settled_at)->toBeNull();
})->group('payments', 'reconciliation');

/**
 * Une ligne entièrement affectée n'a rien à abandonner.
 *
 * Le geste doit rester exceptionnel et visible ; l'ouvrir sur une ligne déjà
 * close en ferait un bouton qu'on clique sans y penser.
 */
it('refuses to write off a transaction that leaves no residue', function (): void {
    $transaction = residueFixture(365.0);

    expect($transaction->isSettled())->toBeTrue()
        ->and($transaction->residue())->toBe(0.0);

    expect(fn (): mixed => (new SettleTransactionResidueAction)($transaction, 'Rien à solder'))
        ->toThrow(DomainException::class);
})->group('payments', 'reconciliation');
