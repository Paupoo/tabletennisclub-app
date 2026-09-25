<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Ouvre la dette du club envers un membre, sur n'importe quelle chose payée.
 *
 * Un remboursement est une ligne à lui. `amount_due` porte l'engagement de
 * rendre, `amount_paid` ne bouge qu'au débit rapproché, et l'encaissement
 * d'origine reste `paid` — l'argent est bel et bien entré, et cela ne cesse pas
 * d'être vrai parce qu'on le rend.
 *
 * Basculer le statut de l'encaissement, comme le faisaient le service des
 * tournois et le semeur, donnait au même enregistrement deux sens à la fois :
 * `amount_paid` y comptait l'argent entré là où le reste du domaine y lit
 * l'argent sorti. Le solde valait zéro, l'écran affichait 0,00 €, et le
 * virement ne pouvait plus être rapproché faute de quoi que ce soit à affecter.
 *
 * {@see RequestSubscriptionRefundAction}
 * fait la même chose pour une affiliation, en y ajoutant sa notification et son
 * plafond métier.
 */
final class OpenRefundAction
{
    /**
     * @param  Payment  $encashment  La ligne qui prouve que l'argent est entré.
     * @param  float|null  $amount  À rendre ; tout l'encaissement par défaut.
     * @param  string|null  $targetIban  Le compte qui a versé, quand ce n'est pas celui du membre.
     */
    public function __invoke(Payment $encashment, ?float $amount = null, ?string $targetIban = null): Payment
    {
        $payable = $encashment->payable;

        $refund = new Payment([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $amount ?? (float) $encashment->amount_paid,
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
            // Un trop-perçu se rend d'où il vient ; à défaut, à son titulaire.
            'refund_iban' => $targetIban ?? $this->memberIban($payable),
        ]);

        // Par la relation morphique : tous les payables ne déclarent pas une
        // collection de paiements, et `payable_type` reste hors de `$fillable`.
        $refund->payable()->associate($payable);
        $refund->save();

        return $refund;
    }

    /**
     * Le compte du membre, quand la chose payée en désigne un.
     *
     * Par la colonne `user_id`, jamais par la relation : le chargement
     * paresseux est interdit hors production, et une migration ou un appel qui
     * n'aurait pas préchargé `payable.user` lèverait une violation au lieu de
     * rendre un IBAN. Une commande de bar n'a pas de membre du tout — le bar ne
     * sait pas qui a payé — et rend donc `null`.
     */
    private function memberIban(?Model $payable): ?string
    {
        $memberId = $payable?->getAttribute('user_id');

        return $memberId === null ? null : User::find($memberId)?->iban;
    }
}
