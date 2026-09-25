<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Support\Facades\DB;

function runBackfillPaymentCreditsMigration(): void
{
    $migration = require base_path('database/migrations/2026_09_21_102700_backfill_payment_credits.php');
    $migration->up();
}

function legacyTransaction(float $amount): Transaction
{
    return Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT',
        'amount' => $amount,
        'counterparty_name' => 'Historique',
    ]);
}

/**
 * Écrit une ligne telle que l'ancien code la laissait : le lien dans une
 * colonne, aucune ligne de crédit.
 *
 * @param  array<string, mixed>  $attributes
 */
function legacyPayment(Subscription $subscription, array $attributes): Payment
{
    static $counter = 0;
    $counter++;

    // Entièrement en direct : on pose l'état brut d'avant migration, colonnes de
    // liaison comprises — et le modèle refuse maintenant la forme que l'ancien
    // code écrivait, un « à rembourser » qui ne se dit pas remboursement.
    $id = DB::table('payments')->insertGetId([
        'reference' => sprintf('100/0000/%05d', $counter),
        'payable_type' => $subscription->getMorphClass(),
        'payable_id' => $subscription->id,
        'amount_due' => (int) round(((float) $attributes['amount_due']) * 100),
        'amount_paid' => (int) round(((float) ($attributes['amount_paid'] ?? 0)) * 100),
        'status' => $attributes['status'],
        'payment_method' => $attributes['payment_method'] ?? 'electronic',
        'transaction_id' => $attributes['transaction_id'] ?? null,
        'refund_transaction_id' => $attributes['refund_transaction_id'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Payment::findOrFail($id);
}

function backfillSubscription(): Subscription
{
    return Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'amount_due' => 365,
    ]);
}

it('turns a reconciled payment into a credit against its transaction', function (): void {
    $subscription = backfillSubscription();
    $transaction = legacyTransaction(365.0);

    $payment = legacyPayment($subscription, [
        'amount_due' => 365,
        'amount_paid' => 365,
        'status' => 'paid',
        'transaction_id' => (string) $transaction->id,
    ]);

    runBackfillPaymentCreditsMigration();

    expect($payment->fresh()->credits)->toHaveCount(1)
        ->and($payment->fresh()->amount_paid)->toBe(365.0)
        ->and($transaction->fresh()->allocated_amount)->toBe(365.0)
        ->and($transaction->fresh()->isSettled())->toBeTrue();
})->group('payments', 'backfill');

/**
 * `payments.transaction_id` est une colonne `string` parce qu'elle a porté deux
 * sens : l'identifiant d'un prestataire de paiement d'un côté, la clé d'une
 * ligne de relevé de l'autre. Seul le second désigne une transaction.
 */
it('credits a payment settled outside the bank without inventing a transaction', function (): void {
    $subscription = backfillSubscription();

    $payment = legacyPayment($subscription, [
        'amount_due' => 125,
        'amount_paid' => 125,
        'status' => 'paid',
        'transaction_id' => 'psp_8f21c4e0',
    ]);

    runBackfillPaymentCreditsMigration();

    $credits = $payment->fresh()->credits;

    expect($credits)->toHaveCount(1)
        ->and($credits->first()->transaction_id)->toBeNull()
        ->and($credits->first()->amount)->toBe(125.0);
})->group('payments', 'backfill');

/**
 * Un remboursement promis portait `amount_paid = amount_due` dès sa création,
 * avant qu'un euro n'ait quitté la banque. La colonne est désormais le miroir
 * de ce qui est sorti : sur une demande non exécutée, c'est zéro.
 */
it('clears the phantom amount on a refund that was never paid out', function (): void {
    $subscription = backfillSubscription();

    $refund = legacyPayment($subscription, [
        'amount_due' => 65,
        'amount_paid' => 65,
        'status' => 'to_refund',
        'payment_method' => 'refund',
    ]);

    runBackfillPaymentCreditsMigration();

    expect($refund->fresh()->amount_paid)->toBe(0.0)
        ->and($refund->fresh()->amount_due)->toBe(65.0)
        ->and($refund->fresh()->credits)->toHaveCount(0);
})->group('payments', 'backfill');

it('keeps what an executed refund actually paid out', function (): void {
    $subscription = backfillSubscription();
    $outgoing = legacyTransaction(-65.0);

    $refund = legacyPayment($subscription, [
        'amount_due' => 65,
        'amount_paid' => 65,
        'status' => 'refunded',
        'payment_method' => 'refund',
        'refund_transaction_id' => $outgoing->id,
    ]);

    runBackfillPaymentCreditsMigration();

    expect($refund->fresh()->amount_paid)->toBe(65.0)
        ->and($refund->fresh()->credits)->toHaveCount(1)
        ->and($outgoing->fresh()->allocated_amount)->toBe(-65.0)
        ->and($outgoing->fresh()->isSettled())->toBeTrue();
})->group('payments', 'backfill');

it('can be run twice without crediting anything twice', function (): void {
    $subscription = backfillSubscription();
    $transaction = legacyTransaction(365.0);

    $payment = legacyPayment($subscription, [
        'amount_due' => 365,
        'amount_paid' => 365,
        'status' => 'paid',
        'transaction_id' => (string) $transaction->id,
    ]);

    runBackfillPaymentCreditsMigration();
    runBackfillPaymentCreditsMigration();

    expect($payment->fresh()->credits)->toHaveCount(1)
        ->and($transaction->fresh()->allocated_amount)->toBe(365.0);
})->group('payments', 'backfill');

/**
 * Le statut tranche quand la méthode ne dit pas la vérité.
 *
 * `TreasurySeeder` écrit `payment_method = 'Wire'` sur des lignes `to_refund`,
 * là où le code applicatif écrit `'refund'`. Une reprise qui ne croirait que la
 * méthode leur donnerait un crédit **entrant** de 120 € : de l'argent inventé,
 * dans le mauvais sens, sur une demande de remboursement.
 *
 * `to_refund` n'est écrit que par RequestSubscriptionRefundAction. C'est le
 * signal fiable sur des données anciennes.
 */
it('reads a to_refund line as a refund whatever its payment method says', function (): void {
    $subscription = backfillSubscription();

    $refund = legacyPayment($subscription, [
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'to_refund',
        'payment_method' => 'Wire',
    ]);

    runBackfillPaymentCreditsMigration();

    expect($refund->fresh()->credits)->toHaveCount(0)
        ->and($refund->fresh()->amount_paid)->toBe(0.0);
})->group('payments', 'backfill');

/**
 * L'autre forme d'un `to_refund` : un paiement encaissé dont on a basculé le
 * statut, transaction entrante comprise.
 *
 * `TreasurySeeder:228` fait exactement ça. L'argent est entré — la ligne garde
 * son crédit, et le club doit toujours le rendre par ailleurs.
 */
it('keeps the incoming credit of a payment whose status was flipped to to_refund', function (): void {
    $subscription = backfillSubscription();
    $incoming = legacyTransaction(120.0);

    $payment = legacyPayment($subscription, [
        'amount_due' => 120,
        'amount_paid' => 120,
        'status' => 'to_refund',
        'payment_method' => 'Wire',
        'transaction_id' => (string) $incoming->id,
    ]);

    runBackfillPaymentCreditsMigration();

    expect($payment->fresh()->amount_paid)->toBe(120.0)
        ->and($payment->fresh()->credits)->toHaveCount(1)
        ->and($incoming->fresh()->allocated_amount)->toBe(120.0);
})->group('payments', 'backfill');
