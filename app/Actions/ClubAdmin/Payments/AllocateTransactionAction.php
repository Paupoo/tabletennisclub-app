<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Ventile une ligne de relevé bancaire sur un ou plusieurs paiements.
 *
 * Le seul chemin d'écriture d'un encaissement. `payments.amount_paid` est un
 * miroir de {@see PaymentCredit} : le
 * recalculer ici, et nulle part ailleurs, est ce qui garantit qu'il dit la
 * vérité.
 */
final class AllocateTransactionAction
{
    /**
     * @param  array<int, float>  $allocations  payment_id => montant en euros
     */
    public function __invoke(Transaction $transaction, array $allocations): void
    {
        DB::transaction(function () use ($transaction, $allocations): void {
            foreach ($allocations as $paymentId => $amount) {
                $payment = Payment::findOrFail($paymentId);

                $payment->credits()->create([
                    'transaction_id' => $transaction->id,
                    'amount' => $amount,
                    'method' => 'transfer',
                    'created_by_id' => Auth::id(),
                ]);

                $this->refreshPaymentMirror($payment);
            }
        });
    }

    /**
     * `sum()` rend des centimes bruts, le mutateur attend des euros.
     */
    private function refreshPaymentMirror(Payment $payment): void
    {
        $payment->update([
            'amount_paid' => round(((float) $payment->credits()->sum('amount')) / 100, 2),
        ]);
    }
}
