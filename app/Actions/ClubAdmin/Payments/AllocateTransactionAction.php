<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
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
     *
     * @throws \DomainException si la ventilation dépasse le montant de la transaction
     */
    public function __invoke(Transaction $transaction, array $allocations): void
    {
        DB::transaction(function () use ($transaction, $allocations): void {
            $this->assertFitsWithinTransaction($transaction, $allocations);

            foreach ($allocations as $paymentId => $amount) {
                $payment = Payment::findOrFail($paymentId);

                $payment->credits()->create([
                    'transaction_id' => $transaction->id,
                    'amount' => $amount,
                    'method' => 'transfer',
                    'created_by_id' => Auth::id(),
                ]);

                $this->refreshPaymentMirror($payment);
                $this->settlePayable($payment);
            }

            $this->refreshTransactionMirror($transaction);
        });
    }

    /**
     * Un encaissement qui ne vient pas de la banque — la caisse, typiquement.
     *
     * `payments.amount_paid` est le miroir des lignes de crédit : tout ce qui
     * entre doit passer par une ligne, sans quoi la colonne cesse d'être
     * vérifiable. Une ligne sans transaction n'est pas un manque, c'est
     * exactement ce qu'un paiement en espèces est.
     */
    public function credit(Payment $payment, float $amount, string $method): void
    {
        DB::transaction(function () use ($payment, $amount, $method): void {
            $payment->credits()->create([
                'transaction_id' => null,
                'amount' => $amount,
                'method' => $method,
                'created_by_id' => Auth::id(),
            ]);

            $this->refreshPaymentMirror($payment);
            $this->settlePayable($payment);
        });
    }

    /**
     * I1 : la somme affectée ne dépasse jamais ce que la banque a bougé.
     *
     * Compté en centimes, parce que c'est l'unité de stockage : comparer des
     * euros flottants ferait dépendre un invariant comptable d'un arrondi.
     *
     * Les affectations déjà posées comptent — une ligne se ventile en plusieurs
     * fois, et l'invariant porte sur le total, pas sur le dernier geste.
     *
     * En valeur absolue : un débit porte un montant négatif, et une sortie de
     * 105 € ne se ventile pas plus qu'une entrée de 105 €.
     *
     * @param  array<int, float>  $allocations
     *
     * @throws \DomainException
     */
    private function assertFitsWithinTransaction(Transaction $transaction, array $allocations): void
    {
        $requested = array_sum(array_map(
            static fn (float|int $amount): int => (int) round(abs((float) $amount) * 100),
            $allocations,
        ));

        $already = abs((int) $transaction->credits()->sum('amount'));
        $capacity = (int) round(abs((float) $transaction->amount) * 100);

        if ($already + $requested > $capacity) {
            throw new \DomainException(__('This allocation exceeds the transaction: only :amount € remain to allocate.', [
                'amount' => number_format(($capacity - $already) / 100, 2, ',', ' '),
            ]));
        }
    }

    /**
     * Le miroir de la ligne, et son statut quand le solde est atteint.
     *
     * `sum()` rend des centimes bruts, le mutateur attend des euros.
     */
    private function refreshPaymentMirror(Payment $payment): void
    {
        $credited = (int) $payment->credits()->sum('amount');

        $attributes = ['amount_paid' => round($credited / 100, 2)];

        $settled = $this->settledStatusFor($payment, $credited);

        if ($settled !== null) {
            $attributes['status'] = $settled;
        }

        $payment->update($attributes);
    }

    /**
     * Recalculé une fois la ventilation entière écrite, jamais par allocation :
     * un miroir mis à jour en cours de route décrirait un état intermédiaire
     * que personne n'a décidé.
     */
    private function refreshTransactionMirror(Transaction $transaction): void
    {
        $allocated = abs((float) $transaction->credits()->sum('amount')) / 100;

        // Le miroir prend le signe de la ligne de relevé. Les crédits, eux,
        // restent positifs : le sens de l'argent est porté par la transaction,
        // comme `payment_method` le porte côté paiement. Sans ce report, une
        // sortie de 105 € entièrement traitée afficherait +105 face à -105 et
        // ne pourrait jamais se dire soldée.
        $sign = (float) $transaction->amount < 0 ? -1 : 1;

        // `forceFill` : le miroir est délibérément hors `$fillable`, pour qu'un
        // `update()` de passage ne puisse pas le contredire.
        $transaction->forceFill([
            'allocated_amount' => round($sign * $allocated, 2),
        ])->save();
    }

    /**
     * Le statut que ce qui vient d'être crédité fait atteindre à la ligne, s'il
     * y en a un.
     *
     * En centimes, sans tolérance à inventer : les deux montants sortent de la
     * même colonne et la comparaison est exacte.
     *
     * Deux sens, deux mots. Une créance soldée devient `paid` ; un
     * remboursement exécuté devient `refunded` — c'est le mot que
     * `confirmRefundReconcile()` emploie déjà, et en changer ferait diverger
     * les deux portes du même geste.
     *
     * Une ligne qui n'attend plus rien — `cancelled`, ou déjà close — n'est pas
     * réécrite par un encaissement.
     */
    private function settledStatusFor(Payment $payment, int $credited): ?string
    {
        if ($credited < (int) round((float) $payment->amount_due * 100)) {
            return null;
        }

        return match (true) {
            $payment->payment_method === 'refund' && $payment->status === 'to_refund' => 'refunded',
            $payment->payment_method !== 'refund' && $payment->status === 'pending' => 'paid',
            default => null,
        };
    }

    /**
     * Ce que l'encaissement change pour la chose payée.
     *
     * Tous les payables ne portent pas d'état de paiement : une commande de bar
     * n'en a pas. Seuls ceux qui en ont un sont touchés.
     */
    private function settlePayable(Payment $payment): void
    {
        // Une ligne de remboursement est de l'argent qui sort : elle ne règle
        // aucune cotisation, et la faire passer par la machine à états de
        // l'affiliation demanderait une transition qui n'a pas lieu d'être.
        if ($payment->payment_method === 'refund') {
            return;
        }

        $payable = $payment->payable;

        if ($payable instanceof Subscription) {
            $this->settleSubscription($payable);

            return;
        }

        // Une inscription au tournoi n'a pas de solde partiel à raconter : le
        // drapeau ne se lève qu'une fois la place entièrement payée.
        if ($payable instanceof TournamentRegistration && $payment->status === 'paid') {
            $payable->update(['has_paid' => true]);
        }
    }

    /**
     * L'affiliation suit l'argent reçu, et ne bascule qu'au solde atteint.
     *
     * `markAsPaid()` était appelé sans condition : 50 € sur une affiliation de
     * 365 € la déclaraient réglée. `isFullyPaid()` existait déjà, avec sa
     * tolérance d'un centime, sans que personne ne l'interroge ici.
     *
     * Le premier euro confirme encore une affiliation restée `pending` : le
     * club a l'argent du membre, et `confirmed_at` — que les mutuelles lisent —
     * doit dater de l'engagement, pas du dernier versement.
     */
    private function settleSubscription(Subscription $subscription): void
    {
        $subscription->forceFill(['amount_paid' => $subscription->totalPaid()])->save();

        if ($subscription->getStatus() === 'pending') {
            $subscription->confirm();
        }

        if (! $subscription->isFullyPaid() || $subscription->getStatus() === 'paid') {
            return;
        }

        $subscription->markAsPaid();
    }
}
