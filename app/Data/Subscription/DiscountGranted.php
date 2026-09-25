<?php

declare(strict_types=1);

namespace App\Data\Subscription;

use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionDiscount;

/**
 * Ce qu'une remise laisse derrière elle.
 *
 * Porte le trop-perçu plutôt que de le laisser deviner : quand la remise
 * descend le montant dû sous ce que le membre a déjà versé, il reste de
 * l'argent au club qui ne lui appartient plus. `GrantSubscriptionDiscountAction`
 * en ouvre le remboursement vers le compte qui a payé ; ce montant permet à
 * l'écran de dire au secrétaire ce qu'il vient de déclencher.
 */
final readonly class DiscountGranted
{
    public function __construct(
        public SubscriptionDiscount $discount,
        /** Le trop-perçu libéré, en euros : déjà encaissé, et dont le remboursement est ouvert. */
        public float $refundable,
    ) {}

    public function leavesMoneyToRefund(): bool
    {
        return $this->refundable > 0.0;
    }
}
