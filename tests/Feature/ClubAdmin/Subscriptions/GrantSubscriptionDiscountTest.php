<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\GrantSubscriptionDiscountAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;

function pricedAffiliation(bool $competitive = false): Subscription
{
    $subscription = Subscription::factory()->create([
        'user_id' => User::factory()->create()->id,
        'status' => 'confirmed',
        'is_competitive' => $competitive,
    ]);

    (new CalculatePriceAction)($subscription);

    return $subscription->fresh();
}

/**
 * Le secrétaire offre 20 € en remerciement.
 *
 * Toute la difficulté est là : `CalculatePriceAction` recalcule `amount_due`
 * **à partir de zéro** à chaque changement — ajout d'un pack, départ, remise
 * famille. Une remise retranchée une seule fois, au moment de l'octroi,
 * disparaîtrait au premier de ces événements. C'est pour cette raison que
 * `family_credit` est une colonne et pas un calcul, et la remise doit suivre
 * la même règle.
 */
it('takes the discount off what the affiliation owes, and keeps it off', function (): void {
    $subscription = pricedAffiliation();

    expect($subscription->amount_due)->toBe(60.0);

    (new GrantSubscriptionDiscountAction)($subscription, 20.0, 'Remerciement — buvette toute la saison');

    expect($subscription->fresh()->amount_due)->toBe(40.0);

    // Le recalcul qu'un ajout de pack déclencherait.
    (new CalculatePriceAction)($subscription->fresh());

    expect($subscription->fresh()->amount_due)->toBe(40.0);
})->group('subscriptions', 'discount');

/**
 * La remise arrive après que la communication est partie.
 *
 * `ReduceOutstandingInvoiceAction` tient déjà cet invariant quand `amount_due`
 * baisse pour une autre raison — un pack retiré. Sa docstring dit le cas exact :
 * « une affiliation à 365 € dont on retirait deux packs retombait à 213 € dus,
 * et continuait de réclamer 365 € ». Une remise fait exactement la même chose
 * au montant dû, et le membre recevrait une relance pour une somme qu'il ne
 * doit plus.
 */
it('reduces a claim already sent when the discount lands after invoicing', function (): void {
    $subscription = pricedAffiliation(competitive: true);

    expect($subscription->amount_due)->toBe(125.0);

    $payment = $subscription->payments()->create([
        'reference' => '500/0000/00001',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Accord parents séparés');

    expect($subscription->fresh()->amount_due)->toBe(100.0)
        ->and($payment->fresh()->amount_due)->toBe(100.0)
        ->and($payment->fresh()->status)->toBe('pending');
})->group('subscriptions', 'discount');

/**
 * Plusieurs octrois s'additionnent, et le prix ne passe jamais sous zéro.
 *
 * Le plancher est délibéré : un club peut offrir plus que ce qu'il réclame —
 * une affiliation entièrement offerte — sans que la facture devienne une
 * créance du membre sur le club.
 */
it('accumulates several grants and never drives the price below zero', function (): void {
    $subscription = pricedAffiliation();

    (new GrantSubscriptionDiscountAction)($subscription, 20.0, 'Remerciement buvette');
    (new GrantSubscriptionDiscountAction)($subscription->fresh(), 50.0, 'Geste supplémentaire du comité');

    expect($subscription->fresh()->discountTotal())->toBe(70.0)
        ->and($subscription->fresh()->amount_due)->toBe(0.0);
})->group('subscriptions', 'discount');

it('refuses a discount without a reason, and one worth nothing', function (): void {
    $subscription = pricedAffiliation();

    expect(fn (): mixed => (new GrantSubscriptionDiscountAction)($subscription, 20.0, '   '))
        ->toThrow(DomainException::class);

    expect(fn (): mixed => (new GrantSubscriptionDiscountAction)($subscription, 0.0, 'Motif valable'))
        ->toThrow(DomainException::class);

    expect($subscription->fresh()->discounts)->toHaveCount(0)
        ->and($subscription->fresh()->amount_due)->toBe(60.0);
})->group('subscriptions', 'discount');

/**
 * La remise descend le dû sous ce que le membre a déjà versé.
 *
 * Il reste alors de l'argent au club qui ne lui appartient plus.
 * `ReduceOutstandingInvoiceAction` le renvoie à l'appelant, et la remise en
 * ouvre le remboursement — voir DiscountOpensRefundTest. Le montant reste
 * nommé pour que le secrétaire l'apprenne au moment où il l'a causé.
 */
it('names the money that must go back when the discount lands on a paid affiliation', function (): void {
    $subscription = pricedAffiliation(competitive: true);

    $subscription->payments()->create([
        'reference' => '500/0000/00002',
        'amount_due' => 125,
        'amount_paid' => 125,
        'status' => 'paid',
    ]);

    $granted = (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Geste du comité en fin de saison');

    expect($granted->refundable)->toBe(25.0)
        ->and($granted->leavesMoneyToRefund())->toBeTrue()
        ->and($subscription->fresh()->amount_due)->toBe(100.0);
})->group('subscriptions', 'discount');

/**
 * Le cas courant ne laisse rien à rendre, et doit le dire aussi clairement.
 */
it('leaves nothing to refund when the member had not paid yet', function (): void {
    $subscription = pricedAffiliation(competitive: true);

    $subscription->payments()->create([
        'reference' => '500/0000/00003',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $granted = (new GrantSubscriptionDiscountAction)($subscription, 25.0, 'Accord parents séparés');

    expect($granted->leavesMoneyToRefund())->toBeFalse()
        ->and($granted->refundable)->toBe(0.0);
})->group('subscriptions', 'discount');
