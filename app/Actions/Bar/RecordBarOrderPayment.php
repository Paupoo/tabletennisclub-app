<?php

declare(strict_types=1);

namespace App\Actions\Bar;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
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
            $this->release($order);

            return null;
        }

        // Idempotent : un double clic sur « Payer », comme une reprise rejouée,
        // ne doit pas donner deux lignes à rapprocher pour un seul virement.
        if ($order->payment()->exists()) {
            return null;
        }

        // D'ordinaire le QR affiché a déjà réservé la ligne ; ici, une commande
        // réglée par QR sans que l'écran l'ait montré (une reprise, un rattrapage).
        return $this->reserve($order);
    }

    /**
     * Rendre la ligne qu'un QR affiché avait réservée, quand la commande se règle
     * autrement ou disparaît.
     *
     * Seulement tant que rien n'est arrivé sur le compte : une ligne déjà
     * rapprochée dit qu'un virement a eu lieu, et c'est au trésorier d'en décider.
     */
    public function release(BarOrder $order): void
    {
        $order->payment()
            ->where('status', 'pending')
            ->whereNull('transaction_id')
            ->where('amount_paid', 0)
            ->delete();
    }

    /**
     * La ligne qu'un QR affiché fait naître, en attente, avec sa communication.
     *
     * Dès l'affichage et non au « Paiement reçu » : le générateur de communication
     * ne réserve une référence que par la ligne qui la porte, et un client qui a
     * scanné puis payé sans que le barman valide doit quand même trouver sa ligne
     * chez le trésorier. Rouvrir le QR rend la même ligne, au montant du moment.
     */
    public function reserve(BarOrder $order): Payment
    {
        $payment = $order->payment()->first();

        if ($payment === null) {
            return $order->payment()->create([
                'reference' => (new GeneratePaymentReference)(),
                'amount_due' => $order->total_price / 100,
                'amount_paid' => 0,
                'status' => 'pending',
                'payment_method' => self::TREASURY_METHOD,
            ]);
        }

        $payment->update(['amount_due' => $order->total_price / 100]);

        return $payment;
    }
}
