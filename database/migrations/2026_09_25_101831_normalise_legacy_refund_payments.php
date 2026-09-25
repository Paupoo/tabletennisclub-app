<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use Illuminate\Database\Migrations\Migration;

/**
 * Sépare les deux faits qu'un `to_refund` hérité confondait.
 *
 * Deux formes ont coexisté sous ce statut. La ligne dédiée que crée
 * {@see RequestSubscriptionRefundAction}
 * porte l'engagement de rendre : `amount_paid` y compte ce qui est **sorti**.
 * L'autre est un paiement bel et bien encaissé dont on a basculé le statut ;
 * `amount_paid` y compte ce qui est **entré**, et la ligne garde le virement
 * entrant qui l'a soldée.
 *
 * Sous un même statut, deux sens opposés. Aucun écran ne peut afficher un
 * chiffre juste pour les deux — le tableau montrait 0,00 € là où la modale
 * annonçait le montant plein — et surtout la seconde forme ne s'exécute pas :
 * son solde vaut zéro, et l'affectation du débit est refusée faute de quoi que
 * ce soit à affecter. La ligne restait dans l'onglet pour toujours.
 *
 * Le virement entrant est ce qui sépare les deux formes, comme dans
 * `2026_09_21_102700_backfill_payment_credits`. Une ligne sans lui a été créée
 * pour rendre de l'argent ; une ligne avec lui en a reçu.
 *
 * Idempotente : une fois l'encaissement rendu à `paid`, il ne ressort plus du
 * filtre.
 */
return new class extends Migration
{
    /**
     * Irréversible, et c'est un choix.
     *
     * Une fois la scission faite, rien ne distingue le remboursement créé ici
     * d'un remboursement ouvert par l'action : même méthode, même statut, aucun
     * encaissement porté. Deviner sur l'appariement des montants détruirait une
     * dette réelle du club — et remettrait en « à rembourser » une cotisation
     * bel et bien reçue. Le cas est couvert par
     * tests/Feature/ClubAdmin/Payment/NormaliseLegacyRefundTest.php.
     *
     * Pour revenir en arrière : restaurer la sauvegarde d'avant migration.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Cette migration scinde une ligne en deux et ne sait pas recoller : '
            . "un remboursement créé ici est indiscernable d'un remboursement ouvert "
            . "normalement. Restaurer la sauvegarde d'avant migration."
        );
    }

    public function up(): void
    {
        Payment::where('status', 'to_refund')
            ->where('payment_method', '!=', 'refund')
            ->whereNotNull('transaction_id')
            ->with('payable')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $encashment) {
                    $this->split($encashment);
                }
            });
    }

    /**
     * L'encaissement redevient ce qu'il est — reçu — et la dette du club prend
     * sa propre ligne.
     */
    private function split(Payment $encashment): void
    {
        $owed = (float) $encashment->amount_paid;

        if ($owed <= 0.0) {
            return;
        }

        $encashment->payable?->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $owed,
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
            'refund_iban' => $encashment->payable->user?->iban,
        ]);

        $encashment->update(['status' => 'paid']);
    }
};
