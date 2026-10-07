<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Support\Collection;

/**
 * Ramène ce qu'on réclame encore sur ce que le membre doit.
 *
 * {@see AddMemberToTrainingPackAction} tient déjà l'invariant dans l'autre sens :
 * un pack ajouté crée sa ligne de complément, et un test porte son nom — « always
 * leaves invoiced and owed in agreement ». À la baisse, rien ne le tenait. Une
 * affiliation à 365 € dont on retirait deux packs retombait à 213 € dus, et
 * continuait de réclamer 365 € : le membre recevait une communication pour un
 * montant qu'il ne devait plus.
 *
 * Ce n'est pas un remboursement, et c'est la distinction qui compte : tant que
 * rien n'est rentré, il n'y a rien à rendre — seulement moins à demander. Ce qui
 * ne peut plus être retiré de la facture, parce qu'il a déjà été encaissé, est
 * renvoyé à l'appelant, à qui revient d'ouvrir un remboursement.
 */
class ReduceOutstandingInvoiceAction
{
    /**
     * @return float Le trop-perçu qui subsiste, en euros : déjà encaissé, donc
     *               seul un remboursement peut le rendre.
     */
    public function __invoke(Subscription $subscription): float
    {
        $subscription->load('payments');

        $claims = $this->claims($subscription);
        $excess = $this->excess($subscription, (float) $subscription->amount_due);

        if ($excess <= 0.0) {
            return 0.0;
        }

        // La plus récente d'abord : c'est le complément qu'on vient d'ajouter, et
        // celui dont le membre n'a pas encore la communication sous les yeux.
        foreach ($claims->where('status', 'pending')->sortByDesc('id') as $payment) {
            if ($excess <= 0.0) {
                break;
            }

            // Jamais sous ce qui est déjà rentré sur cette ligne : le reste est
            // un trop-perçu, et un trop-perçu se rembourse, il ne s'efface pas.
            $reducible = round((float) $payment->amount_due - (float) $payment->amount_paid, 2);

            if ($reducible <= 0.0) {
                continue;
            }

            $taken = min($excess, $reducible);

            if ($taken >= (float) $payment->amount_due) {
                // Plus rien à réclamer sur cette ligne. On l'annule plutôt que de
                // la détruire ou de la laisser à zéro : c'est le mot que le reste
                // du domaine emploie, et la référence structurée reste lisible
                // pour un membre qui l'aurait déjà reçue.
                $payment->update(['status' => 'cancelled']);
            } else {
                $payment->update(['amount_due' => round((float) $payment->amount_due - $taken, 2)]);

                // Baissée jusqu'à ce qui est déjà rentré, la ligne est soldée.
                // Son payable est l'affiliation qu'on tient déjà : chargée en
                // lot, la ligne ne pourrait pas aller le chercher seule.
                (new AllocateTransactionAction)->settleIfCovered($payment->setRelation('payable', $subscription));
            }

            $excess = round($excess - $taken, 2);
        }

        $subscription->load('payments');

        return max(0.0, $excess);
    }

    /**
     * Ce que l'action ferait si le dû tombait à `$amountDue`, sans rien écrire.
     *
     * Sert aux aperçus : annoncer « demande réduite de X €, Y € à rembourser »
     * avant de valider. Les deux chiffres sortent du même raisonnement que
     * l'action, pour qu'un aperçu ne puisse pas promettre autre chose qu'elle.
     *
     * @return array{reduced: float, overpaid: float}
     */
    public function project(Subscription $subscription, float $amountDue): array
    {
        $subscription->loadMissing('payments');

        $excess = $this->excess($subscription, $amountDue);

        if ($excess <= 0.0) {
            return ['reduced' => 0.0, 'overpaid' => 0.0];
        }

        $reducible = round((float) $this->claims($subscription)
            ->where('status', 'pending')
            ->sum(fn (Payment $payment): float => max(0.0, (float) $payment->amount_due - (float) $payment->amount_paid)), 2);

        $reduced = min($excess, $reducible);

        return ['reduced' => round($reduced, 2), 'overpaid' => round($excess - $reduced, 2)];
    }

    /**
     * Les lignes qui réclament encore quelque chose.
     *
     * Une ligne de remboursement n'est pas une créance, une ligne annulée
     * n'en est plus une.
     *
     * @return Collection<int, Payment>
     */
    private function claims(Subscription $subscription): Collection
    {
        return $subscription->payments->filter(
            fn (Payment $payment): bool => $payment->payment_method !== 'refund'
                && $payment->status !== 'cancelled'
        );
    }

    /**
     * Ce qui est réclamé au-delà de `$amountDue`.
     *
     * Un remboursement déjà dans le circuit a soldé sa part de la facture,
     * même s'il n'a pas encore quitté la banque. L'ignorer ferait compter
     * deux fois les mêmes euros au départ suivant — même raisonnement que
     * {@see Subscription::netAmountPaid()}, sur les montants dus cette fois.
     */
    private function excess(Subscription $subscription, float $amountDue): float
    {
        $issued = round((float) $subscription->payments
            ->filter(fn (Payment $payment): bool => $payment->payment_method === 'refund'
                && in_array($payment->status, Payment::REFUND_COMMITTED_STATUSES, true))
            ->sum(fn (Payment $payment): float => (float) $payment->amount_due), 2);

        return round(
            $this->claims($subscription)->sum(fn (Payment $payment): float => (float) $payment->amount_due)
                - $issued
                - $amountDue,
            2
        );
    }
}
