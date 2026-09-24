<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Data\Subscription\DiscountGranted;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionDiscount;
use Illuminate\Support\Collection;
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
            $pendingBefore = $subscription->payments()->where('status', 'pending')->get()
                ->mapWithKeys(fn (Payment $payment): array => [$payment->id => $payment->amount_due]);

            $refundable = (new ReduceOutstandingInvoiceAction)($subscription);

            $this->linkToAbsorbingPayment($discount, $subscription, $pendingBefore);

            return new DiscountGranted($discount->fresh(), $refundable);
        });
    }

    /**
     * Lie la remise à la communication qui l'a absorbée, pour que le membre la
     * lise à côté du montant qu'elle explique.
     *
     * Seulement quand une seule communication a baissé, et exactement du
     * montant remis : une remise répartie sur plusieurs, ou en partie
     * remboursée, n'a pas de « prix normal » honnête à afficher sur l'une
     * d'elles.
     *
     * @param  Collection<int, float>  $pendingBefore  Montant dû par communication en attente, avant réduction.
     */
    private function linkToAbsorbingPayment(SubscriptionDiscount $discount, Subscription $subscription, Collection $pendingBefore): void
    {
        $reduced = $subscription->payments
            ->filter(fn (Payment $payment): bool => $pendingBefore->has($payment->id))
            ->mapWithKeys(fn (Payment $payment): array => [
                $payment->id => round($pendingBefore[$payment->id] - ($payment->status === 'cancelled' ? 0.0 : $payment->amount_due), 2),
            ])
            ->filter(fn (float $reduction): bool => $reduction > 0.0);

        if ($reduced->count() === 1 && $reduced->first() === round($discount->amount, 2)) {
            $discount->update(['payment_id' => $reduced->keys()->first()]);
        }
    }
}
