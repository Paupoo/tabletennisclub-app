<?php

declare(strict_types=1);

namespace App\Data\Subscription;

use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionDiscount;

/**
 * Ce qu'une remise laisse derrière elle.
 *
 * Porte le trop-perçu plutôt que de le laisser deviner : quand la remise
 * descend le montant dû sous ce que le membre a déjà versé, il reste de
 * l'argent au club qui ne lui appartient plus. Rien ne le rend automatiquement
 * — `ReduceOutstandingInvoiceAction` prend déjà ce parti, et le remboursement
 * est un geste de trésorerie, pas une conséquence d'un clic du secrétariat.
 *
 * Mais il doit être dit, sinon personne ne le saura.
 */
final readonly class DiscountGranted
{
    public function __construct(
        public SubscriptionDiscount $discount,
        /** Le trop-perçu qui subsiste, en euros : déjà encaissé, donc seul un remboursement peut le rendre. */
        public float $refundable,
    ) {}

    public function leavesMoneyToRefund(): bool
    {
        return $this->refundable > 0.0;
    }
}
