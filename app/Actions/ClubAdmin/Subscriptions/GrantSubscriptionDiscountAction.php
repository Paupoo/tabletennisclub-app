<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Data\Subscription\DiscountGranted;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Accorde une remise sur une affiliation.
 *
 * Le geste du secrétaire qui offre — un remerciement, un accord honoré. Il se
 * décide à un instant, sur un montant connu, et se **gèle en euros** : le
 * pourcentage n'est qu'une façon de saisir, pas une promesse qui suivrait le
 * prix. Si le membre ajoute un entraînement en janvier, on lui redemande.
 *
 * Le motif est obligatoire, comme partout ici où un humain décide d'un
 * montant : une affiliation dont le prix ne s'explique ni par le barème ni par
 * une phrase n'est pas défendable devant le membre.
 */
final class GrantSubscriptionDiscountAction
{
    /**
     * @param  float  $amount  En euros.
     * @return DiscountGranted La remise, et le trop-perçu qu'elle laisse s'il y en a un.
     *
     * @throws \DomainException
     */
    public function __invoke(
        Subscription $subscription,
        float $amount,
        string $reason,
        int $familyMembersCount = 1,
    ): DiscountGranted {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \DomainException(__('A reason is required to grant a discount.'));
        }

        if ($amount <= 0.0) {
            throw new \DomainException(__('A discount must be worth more than nothing.'));
        }

        return DB::transaction(function () use ($subscription, $amount, $reason, $familyMembersCount): DiscountGranted {
            $discount = $subscription->discounts()->create([
                'amount' => $amount,
                'reason' => $reason,
                'granted_by_id' => Auth::id(),
                'granted_at' => now(),
            ]);

            // Le prix se refait entièrement : la remise n'est pas soustraite du
            // montant courant, elle entre dans le calcul comme le crédit
            // famille, et c'est ce qui la rend insensible aux recalculs.
            $subscription = (new CalculatePriceAction)($subscription->fresh(), $familyMembersCount);

            // Ce qu'on réclame encore doit suivre ce qu'on doit. Sans ça, une
            // affiliation remisée continuerait d'envoyer des relances pour le
            // montant d'avant — le défaut que ReduceOutstandingInvoiceAction a
            // été écrite pour fermer, dans l'autre sens.
            $refundable = (new ReduceOutstandingInvoiceAction)($subscription);

            return new DiscountGranted($discount->fresh(), $refundable);
        });
    }
}
