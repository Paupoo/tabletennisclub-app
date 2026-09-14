<?php

declare(strict_types=1);

namespace App\Actions\Bar;

use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;

/**
 * Fait entrer une commande du bar dans la liste à rapprocher du trésorier.
 *
 * Seul le QR passe par ici. Le cash et l'offert n'ont pas de contrepartie
 * bancaire : les verser dans cet écran le noierait sous des lignes de 2,50 €
 * qu'aucune transaction ne viendrait jamais rapprocher. Ils relèvent du
 * tiroir-caisse, qui est un autre sujet.
 *
 * Le paiement naît **en attente**, jamais payé. Le barman a encaissé, mais
 * l'argent n'est pas encore sur le compte du club : c'est exactement ce que le
 * trésorier doit rapprocher, et c'est ce qui rend visible un virement annoncé
 * au comptoir puis jamais exécuté.
 */
class RecordBarOrderPayment
{
    /**
     * La méthode d'encaissement qui a une contrepartie sur le compte du club.
     *
     * `other` existe dans d'anciennes données mais pas au comptoir : la
     * validation de l'écran de paiement n'accepte que `cash`, `offered` et `qr`.
     */
    public const string BANKED_METHOD = 'qr';

    /**
     * Le moyen de paiement tel que l'écran du trésorier le filtre déjà.
     */
    private const string TREASURY_METHOD = 'QRCode';

    /**
     * @return Payment|null Le paiement créé, ou null si la commande n'a pas à remonter.
     */
    public function __invoke(BarOrder $order): ?Payment
    {
        if ($order->payment_method !== self::BANKED_METHOD) {
            return null;
        }

        // Idempotent : un double clic sur « Payer », comme une reprise rejouée,
        // ne doit pas donner deux lignes à rapprocher pour un seul virement.
        if ($order->payment()->exists()) {
            return null;
        }

        return $order->payment()->create([
            // La communication que le QR bancaire montre déjà au client. La ligne
            // du relevé et celle du trésorier portent ainsi le même texte, au
            // lieu de se chercher au montant et à la date.
            'reference' => "Bar order #{$order->id}",
            'amount_due' => $order->total_price / 100,
            'amount_paid' => 0,
            'status' => 'pending',
            'payment_method' => self::TREASURY_METHOD,
        ]);
    }
}
