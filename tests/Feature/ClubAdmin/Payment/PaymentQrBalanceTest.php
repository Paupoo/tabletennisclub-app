<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;

/**
 * Le QR encode ce qu'il reste à payer.
 *
 * Il encodait `amount_due`. Tant que « partiellement payé » n'existait pas,
 * c'était la même chose que le solde ; ce n'est plus le cas. Un membre qui a
 * versé 200 € sur 365 € et qui scanne sa relance se verrait proposer un
 * virement de 365 € — il paierait 565 € en tout.
 */
function qrTextFor(float $due, float $paid): string
{
    Club::factory()->ownClub()->create(['bic' => 'GEBABEBB', 'bank_account' => 'BE68539007547034']);

    $member = User::factory()->create();

    $subscription = Subscription::factory()->create([
        'user_id' => $member->id,
        'status' => 'confirmed',
        'amount_due' => $due,
    ]);

    $payment = $subscription->payments()->create([
        'reference' => sprintf('123/4567/%05d', random_int(1, 99999)),
        'amount_due' => $due,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    if ($paid > 0.0) {
        $transaction = Transaction::create([
            'date' => now()->toDateString(),
            'description' => 'VIREMENT',
            'amount' => $paid,
            'counterparty_name' => $member->full_name,
        ]);

        (new AllocateTransactionAction)($transaction, [$payment->id => $paid]);
    }

    return (new GeneratePaymentQR)->qrText($payment->fresh());
}

it('encodes what is still owed, not what was originally claimed', function (): void {
    $qr = qrTextFor(365.0, 200.0);

    expect($qr)->toContain('EUR165.00')
        ->and($qr)->not->toContain('EUR365.00');
})->group('payments', 'qr');

it('encodes the full amount when nothing has been paid yet', function (): void {
    expect(qrTextFor(365.0, 0.0))->toContain('EUR365.00');
})->group('payments', 'qr');

/**
 * Un paiement qui n'existe pas en base a quand même droit à son QR.
 *
 * Le bar construit un `Payment` transitoire — `amount_due` et une référence,
 * rien d'autre — pour afficher un QR au client. `amount_paid` y est donc
 * `null`, et les accesseurs de Payment type-hintaient `int` là où ceux de
 * Subscription acceptent déjà `?int`. Lire le solde a suffi à le révéler.
 */
it('builds a QR for a payment that was never saved', function (): void {
    Club::factory()->ownClub()->create(['bic' => 'GEBABEBB', 'bank_account' => 'BE68539007547034']);

    $transient = new Payment([
        'amount_due' => 18.0,
        'reference' => 'Bar order #42',
    ]);

    expect((new GeneratePaymentQR)->qrText($transient))->toContain('EUR18.00');
})->group('payments', 'qr');
