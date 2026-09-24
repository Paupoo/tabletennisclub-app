<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Actions\ClubAdmin\Subscriptions\GrantSubscriptionDiscountAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Subscriptions\Models\SubscriptionDiscount;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Support\Facades\Gate;

/**
 * Proposer une remise au fil d'un autre geste.
 *
 * Le geste canonique vit sur la fiche d'affiliation et couvre tous les cas.
 * Ces champs-ci ne sont qu'un raccourci, posé là où la question se pose
 * naturellement : au moment où le club décide d'un prix. Ils évitent de rouvrir
 * la fiche juste après avoir validé.
 *
 * Le moment de l'appel dépend de l'écran, et ce n'est pas un détail : quand la
 * facture naît du montant dû, la remise doit passer avant ; quand un complément
 * est calculé sur un écart, elle passe après et le rabote.
 */
trait GrantsInlineDiscount
{
    public string $inlineDiscountMode = 'amount';

    public string $inlineDiscountReason = '';

    public float $inlineDiscountValue = 0.0;

    /**
     * Accorde la remise saisie, s'il y en a une.
     *
     * Silencieuse quand les champs sont vides : c'est un raccourci facultatif,
     * et la plupart des validations n'en accordent aucune.
     *
     * Un pourcentage porte sur le prix qu'on est en train de décider, pas sur
     * tout ce que l'affiliation doit : 10 % sur deux packs à 160 € font 16 €,
     * pas 10 % de la cotisation en plus. Seule la validation d'une affiliation
     * décide du montant entier ; les écrans de pack passent leur complément.
     *
     * @param  float|null  $decidedPrice  L'assiette du pourcentage, en euros ; le montant dû entier si absente.
     * @return SubscriptionDiscount|null La remise accordée, pour que l'appelant la lie à la facture qu'il crée ensuite.
     */
    private function applyInlineDiscount(Subscription $subscription, ?float $decidedPrice = null): ?SubscriptionDiscount
    {
        if ($this->inlineDiscountValue <= 0.0 || trim($this->inlineDiscountReason) === '') {
            return null;
        }

        if (! Gate::allows(Permission::SubscriptionsDiscount->value)) {
            return null;
        }

        $amount = $this->inlineDiscountMode === 'percent'
            ? round(($decidedPrice ?? (float) $subscription->amount_due) * $this->inlineDiscountValue / 100, 2)
            : round($this->inlineDiscountValue, 2);

        $reason = trim($this->inlineDiscountReason);

        // Le pourcentage ne survit que là : il raconte le geste mieux que le
        // montant qu'il a produit.
        if ($this->inlineDiscountMode === 'percent') {
            $reason = __(':percent% — :reason', [
                'percent' => rtrim(rtrim(number_format($this->inlineDiscountValue, 2, ',', ''), '0'), ','),
                'reason' => $reason,
            ]);
        }

        $discount = null;

        try {
            $discount = (new GrantSubscriptionDiscountAction)(
                $subscription,
                $amount,
                $reason,
                $subscription->has_other_family_members ? 2 : 1,
            )->discount;
        } catch (\DomainException $e) {
            $this->error($e->getMessage());
        }

        $this->reset(['inlineDiscountValue', 'inlineDiscountReason', 'inlineDiscountMode']);

        return $discount;
    }
}
