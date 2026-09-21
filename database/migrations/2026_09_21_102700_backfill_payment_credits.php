<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Donne à l'historique les lignes de crédit qu'il n'a jamais eues.
 *
 * Avant ce lot, le lien entre un paiement et son virement vivait dans deux
 * colonnes `unique()` — `transaction_id` et `refund_transaction_id` — qui ne
 * pouvaient en décrire qu'un seul. Tout ce qui a été rapproché jusqu'ici doit
 * se retrouver dans `payment_credits`, sans quoi les écrans de trésorerie
 * afficheraient un passé vierge de tout rapprochement.
 *
 * Idempotente : un paiement qui porte déjà un crédit est laissé tel quel.
 */
return new class extends Migration
{
    private const string NOTE = 'backfill:payment_credits';

    public function down(): void
    {
        DB::table('payment_credits')->where('note', self::NOTE)->delete();

        // Les demandes jamais exécutées retrouvent la valeur que l'ancien code
        // y écrivait dès la création.
        //
        // Toutes les lignes `to_refund`, sans filtrer sur la méthode : `up()`
        // en met à zéro que la méthode ne désigne pas comme remboursement, et
        // un rollback qui ne les couvre pas laisse la donnée détruite. Pour une
        // ligne que `up()` n'a pas touchée, `amount_due` est de toute façon ce
        // qu'elle portait — elle était soldée.
        DB::table('payments')
            ->where('status', 'to_refund')
            ->update(['amount_paid' => DB::raw('amount_due')]);

        DB::table('transactions')->update(['allocated_amount' => 0]);
    }

    public function up(): void
    {
        $knownTransactions = DB::table('transactions')->pluck('id')->flip();

        DB::table('payments')->orderBy('id')->chunkById(200, function ($payments) use ($knownTransactions): void {
            foreach ($payments as $payment) {
                if (DB::table('payment_credits')->where('payment_id', $payment->id)->exists()) {
                    continue;
                }

                $this->isRefund($payment)
                    ? $this->backfillRefund($payment)
                    : $this->backfillEncashment($payment, $knownTransactions);
            }
        });

        $this->refreshTransactionMirrors();
    }

    /**
     * Un encaissement : le montant est ce que la ligne dit avoir reçu.
     *
     * `transaction_id` est une colonne `string` qui a porté deux sens — la clé
     * d'une ligne de relevé, et la référence d'un prestataire de paiement. Seule
     * la première désigne une transaction ; l'autre devient un crédit sans
     * transaction, ce qu'il a toujours été.
     *
     * @param  Collection<int|string, int>  $knownTransactions
     */
    private function backfillEncashment(object $payment, $knownTransactions): void
    {
        $amount = (int) $payment->amount_paid;

        if ($amount === 0) {
            return;
        }

        $raw = $payment->transaction_id;

        $transactionId = $raw !== null && ctype_digit((string) $raw) && $knownTransactions->has((int) $raw)
            ? (int) $raw
            : null;

        $this->credit($payment, $transactionId, $amount);
    }

    /**
     * Un remboursement : `amount_due` porte l'engagement, et c'est lui qui est
     * sorti quand un virement lui est rattaché.
     *
     * Sans virement, rien n'a quitté la banque. L'ancien code écrivait pourtant
     * `amount_paid = amount_due` dès la demande : ce montant fantôme s'efface.
     */
    private function backfillRefund(object $payment): void
    {
        if ($payment->refund_transaction_id === null) {
            DB::table('payments')->where('id', $payment->id)->update(['amount_paid' => 0]);

            return;
        }

        $amount = (int) $payment->amount_due;

        $this->credit($payment, (int) $payment->refund_transaction_id, $amount);

        DB::table('payments')->where('id', $payment->id)->update(['amount_paid' => $amount]);
    }

    private function credit(object $payment, ?int $transactionId, int $amount): void
    {
        DB::table('payment_credits')->insert([
            'payment_id' => $payment->id,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'method' => $transactionId !== null ? 'transfer' : 'legacy',
            'note' => self::NOTE,
            'created_by_id' => null,
            'created_at' => $payment->created_at ?? now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Cette ligne fait-elle sortir de l'argent ?
     *
     * `payment_method` est censé porter le sens de l'argent, et le code
     * applicatif l'honore. Les données, non : `TreasurySeeder` écrit `'Wire'`
     * sur des lignes `to_refund`. S'en tenir à la méthode leur donnerait un
     * crédit entrant — de l'argent inventé, dans le mauvais sens.
     *
     * Mais `to_refund` seul ne suffit pas non plus : deux formes coexistent
     * dans les données.
     *
     *  - RequestSubscriptionRefundAction crée une **ligne séparée**, sans
     *    transaction entrante, qui ne porte que l'engagement de rendre.
     *  - TreasurySeeder bascule le statut d'un **paiement déjà encaissé** ; la
     *    ligne garde alors le virement entrant qui l'a soldée.
     *
     * La transaction entrante est ce qui les sépare. La traiter comme un
     * remboursement effacerait un encaissement qui a bel et bien eu lieu.
     */
    private function isRefund(object $payment): bool
    {
        if ($payment->payment_method === 'refund') {
            return true;
        }

        return $payment->status === 'to_refund' && $payment->transaction_id === null;
    }

    /**
     * Le miroir prend le signe de la ligne de relevé : un débit entièrement
     * traité doit pouvoir se dire soldé, et il ne le peut qu'à signe égal.
     */
    private function refreshTransactionMirrors(): void
    {
        DB::table('transactions')->orderBy('id')->chunkById(200, function ($transactions): void {
            foreach ($transactions as $transaction) {
                $credited = abs((int) DB::table('payment_credits')
                    ->where('transaction_id', $transaction->id)
                    ->sum('amount'));

                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update(['allocated_amount' => ((int) $transaction->amount < 0 ? -1 : 1) * $credited]);
            }
        });
    }
};
