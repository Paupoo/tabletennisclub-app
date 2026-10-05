<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportPaidNotification;
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
            $this->assertNotJustified($transaction);
            $this->assertSomethingToAllocate($allocations);
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
     * Ce que {@see withdraw()} changerait, pour l'annoncer avant de le faire.
     *
     * Lu par la confirmation des deux écrans : les mêmes règles que le retrait,
     * sans rien écrire.
     *
     * @return array{reopens_payment: bool, written_off: float}
     */
    public function previewWithdrawal(PaymentCredit $credit): array
    {
        $credit->loadMissing(['payment', 'transaction']);

        $payment = $credit->payment;
        $transaction = $credit->transaction;

        $remaining = (int) $payment->credits()->whereKeyNot($credit->id)->sum('amount');
        $uncovered = $remaining < (int) round((float) $payment->amount_due * 100);

        $closed = $payment->payment_method === 'refund' ? 'refunded' : 'paid';

        return [
            'reopens_payment' => $uncovered && $payment->status === $closed,
            'written_off' => $transaction instanceof Transaction && $transaction->settled_at !== null
                ? round(abs($transaction->residue()), 2)
                : 0.0,
        ];
    }

    /**
     * Solde une ligne que ses crédits couvrent déjà, sans rien encaisser.
     *
     * Le montant dû peut baisser sous l'argent reçu — une remise, un
     * entraînement retiré. La ligne atteint alors son solde sans qu'aucun
     * encaissement ne passe par ici, et elle restait `pending` à 0 € : relancée
     * pour rien, et l'affiliation jamais réglée. La règle qui décide qu'une
     * ligne est payée reste ici, seule.
     */
    public function settleIfCovered(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $this->refreshPaymentMirror($payment);
            $this->settlePayable($payment);
        });
    }

    /**
     * Retire un rapprochement : le virement n'a jamais payé cette créance.
     *
     * Le pendant exact d'une affectation. La ligne de crédit disparaît et chaque
     * miroir redescend là où il serait sans elle : le paiement, la chose payée,
     * et la ligne de relevé, qui redevient à traiter.
     *
     * Seul l'argent recule. Une affiliation confirmée par ce virement le reste,
     * et sa date aussi : la confirmation se gère ailleurs.
     *
     * La ligne n'est pas gardée en « retirée » : trop de sommes la lisent en SQL
     * brut pour qu'aucune n'oublie de l'exclure. L'historique du paiement dit
     * ce qui a été retiré.
     *
     * @throws \DomainException
     */
    public function withdraw(PaymentCredit $credit): void
    {
        DB::transaction(function () use ($credit): void {
            $credit->loadMissing(['payment', 'transaction']);

            $transaction = $credit->transaction;
            $payment = $credit->payment;

            if (! $transaction instanceof Transaction) {
                throw new \DomainException(__('Only a bank transfer can be removed from a payment.'));
            }

            $this->assertNoRefundCommitted($payment);

            $credit->delete();

            $this->reopenPaymentMirror($payment);
            $this->unsettlePayable($payment);

            $this->refreshTransactionMirror($transaction);
            $this->reopenWrittenOffResidue($transaction);

            $this->auditWithdrawal($payment, $transaction, $credit);
        });
    }

    /**
     * I1 : la somme affectée ne dépasse jamais le montant de la ligne de relevé.
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
     * Retirer l'argent d'une créance dont une partie est déjà promise au
     * membre ferait rendre ce que le club n'a, d'après l'écran, jamais reçu.
     * Annuler le remboursement reste un geste du trésorier : il peut être déjà
     * parti de la banque.
     *
     * @throws \DomainException
     */
    private function assertNoRefundCommitted(Payment $payment): void
    {
        if ($payment->payment_method === 'refund') {
            return;
        }

        $committed = $payment->refundsCommitted();

        if ($committed > 0) {
            throw new \DomainException(__('A refund of :amount € is committed on this payment: cancel it first.', [
                'amount' => number_format($committed, 2, ',', ' '),
            ]));
        }
    }

    /**
     * Une ligne justifiée par une pièce est de l'argent hors site : y placer
     * aussi le paiement d'un membre mêlerait les deux sur une même ligne, et
     * le rapport financier la compterait deux fois.
     *
     * @throws \DomainException
     */
    private function assertNotJustified(Transaction $transaction): void
    {
        if ($transaction->supportingDocuments()->exists()) {
            throw new \DomainException(__('This transaction is justified by a supporting document: no payment of the website can be allocated to it.'));
        }
    }

    /**
     * Affecter zéro n'est pas une affectation.
     *
     * Rapprocher une créance déjà soldée écrivait une ligne de crédit vide et
     * annonçait un succès. Le grand livre gagnait une ligne qui ne dit rien, et
     * le trésorier croyait avoir fait quelque chose.
     *
     * @param  array<int, float>  $allocations
     *
     * @throws \DomainException
     */
    private function assertSomethingToAllocate(array $allocations): void
    {
        $total = array_sum(array_map(
            static fn (float|int $amount): int => (int) round(abs((float) $amount) * 100),
            $allocations,
        ));

        if ($total === 0) {
            throw new \DomainException(__('There is nothing left to allocate on this payment.'));
        }
    }

    /**
     * La ligne de crédit n'est pas auditée, et elle vient de disparaître : sans
     * cette entrée, plus rien ne dirait quel virement avait été placé ici.
     * Écrite sur le paiement, dans la forme que l'écran d'audit sait lire.
     */
    private function auditWithdrawal(Payment $payment, Transaction $transaction, PaymentCredit $credit): void
    {
        activity()
            ->performedOn($payment)
            ->event('reconciliation_removed')
            ->withChanges([
                'old' => [
                    'transaction' => $transaction->id,
                    'transaction_date' => $transaction->date->format('d/m/Y'),
                    'counterparty' => $transaction->counterparty_name,
                    'amount' => number_format((float) $credit->amount, 2, ',', ' ') . ' €',
                    'placed_on' => $credit->created_at?->format('d/m/Y'),
                ],
                'attributes' => ['transaction' => null],
            ])
            ->log('reconciliation_removed');
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
     * Le miroir de la ligne après un retrait, et son statut s'il ne tient plus.
     *
     * L'inverse de {@see settledStatusFor()}, avec la même règle en centimes :
     * une créance qui n'est plus couverte redevient `pending`, un remboursement
     * qui n'est plus sorti redevient `to_refund`. Gardé à part de
     * {@see refreshPaymentMirror()} : un encaissement ne fait jamais reculer un
     * statut.
     */
    private function reopenPaymentMirror(Payment $payment): void
    {
        $credited = (int) $payment->credits()->sum('amount');

        $attributes = ['amount_paid' => round($credited / 100, 2)];

        if ($credited < (int) round((float) $payment->amount_due * 100)) {
            $reopened = match (true) {
                $payment->payment_method === 'refund' && $payment->status === 'refunded' => 'to_refund',
                $payment->payment_method !== 'refund' && $payment->status === 'paid' => 'pending',
                default => null,
            };

            if ($reopened !== null) {
                $attributes['status'] = $reopened;
            }
        }

        $payment->update($attributes);
    }

    /**
     * Un reliquat abandonné l'était au vu de ce qui était placé sur la ligne.
     * Le placement retiré, la décision ne tient plus : la ligne entière
     * redevient à traiter.
     */
    private function reopenWrittenOffResidue(Transaction $transaction): void
    {
        if ($transaction->settled_at === null) {
            return;
        }

        $transaction->forceFill([
            'settled_at' => null,
            'settled_reason' => null,
            'settled_by_id' => null,
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
            $this->settleRefund($payment);

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
     * Un remboursement exécuté ne change rien à la chose remboursée — sauf une
     * note de frais, dont c'est l'aboutissement : le membre apprend que
     * l'argent est parti.
     *
     * Rien n'est écrit sur la note : « payée » se lit sur ce remboursement.
     * Seul le passage à `refunded` compte, pour qu'un second crédit sur une
     * ligne déjà close ne prévienne pas deux fois.
     */
    private function settleRefund(Payment $payment): void
    {
        if ($payment->status !== 'refunded' || ! $payment->wasChanged('status')) {
            return;
        }

        $payable = $payment->payable;

        if ($payable instanceof ExpenseReport) {
            $payable->loadMissing('user');

            DB::afterCommit(fn () => $payable->user->notify(new ExpenseReportPaidNotification($payable)));
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

    /**
     * Ce que le retrait défait pour la chose payée.
     *
     * Une note de frais n'a rien d'écrit sur elle : « payée » se lit sur son
     * remboursement, qui vient de redevenir `to_refund`. Le membre n'est pas
     * prévenu ; le mail « payée » repartira au bon rapprochement.
     */
    private function unsettlePayable(Payment $payment): void
    {
        if ($payment->payment_method === 'refund') {
            return;
        }

        $payable = $payment->payable;

        if ($payable instanceof Subscription) {
            $payable->forceFill(['amount_paid' => $payable->totalPaid()])->save();

            if ($payable->getStatus() === 'paid' && ! $payable->isFullyPaid()) {
                $payable->unpay();
            }

            return;
        }

        if ($payable instanceof TournamentRegistration && $payment->status !== 'paid') {
            $payable->update(['has_paid' => false]);
        }
    }
}
