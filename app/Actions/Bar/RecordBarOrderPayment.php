<?php

declare(strict_types=1);

namespace App\Actions\Bar;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Fait entrer l'argent d'une commande du bar en trésorerie, là où il est arrivé.
 *
 * Le QR va dans la liste à rapprocher du trésorier. Le cash va dans la caisse
 * du club : un mouvement par commande, lié à elle, que le rapport financier
 * range de lui-même en « Bar ». L'offert ne va nulle part, aucun argent
 * n'entre. Le cash n'a pas sa place dans la liste à rapprocher : il la
 * noierait sous des lignes de 2,50 € qu'aucune transaction ne viendrait
 * jamais rapprocher.
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
     * La méthode d'encaissement qui met des espèces dans la caisse du club.
     */
    public const string CASH_METHOD = 'cash';

    /**
     * Le motif d'un mouvement de caisse né d'une vente du bar.
     */
    public const string TILL_REASON = 'bar_sale';

    /**
     * Le moyen de paiement tel que l'écran du trésorier le filtre déjà.
     */
    private const string TREASURY_METHOD = 'QRCode';

    /**
     * @return Payment|null Le paiement créé, ou null si la commande n'a pas à remonter.
     */
    public function __invoke(BarOrder $order): ?Payment
    {
        return DB::transaction(function () use ($order): ?Payment {
            $order = BarOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_method !== self::BANKED_METHOD) {
                $this->release($order);

                if ($order->payment_method === self::CASH_METHOD) {
                    $this->putInTill($order);
                }

                return null;
            }

            // Idempotent : un double clic sur « Payer », comme une reprise rejouée,
            // ne doit pas donner deux lignes à rapprocher pour un seul virement.
            if ($order->payment()->exists()) {
                return null;
            }

            // D'ordinaire le QR affiché a déjà réservé la ligne ; ici, une commande
            // réglée par QR sans que l'écran l'ait montré (une reprise, un rattrapage).
            return $this->createPayment($order);
        });
    }

    /**
     * Le mouvement de caisse d'une commande réglée en espèces.
     *
     * Idempotent comme la ligne du QR : une reprise rejouée ne met pas deux fois
     * le même billet dans la caisse. Inscrit au nom de celui qui encaisse ; lors
     * d'une reprise, au nom du barman qui a servi, et de `$recordedBy` quand son
     * compte n'existe plus.
     *
     * @return CashRegisterEntry|null Le mouvement créé, ou null s'il existait déjà.
     */
    public function putInTill(BarOrder $order, ?int $recordedBy = null): ?CashRegisterEntry
    {
        $alreadyIn = CashRegisterEntry::query()
            ->where('payable_type', BarOrder::class)
            ->where('payable_id', $order->id)
            ->exists();

        if ($alreadyIn) {
            return null;
        }

        return CashRegisterEntry::create([
            'cash_register_id' => CashRegister::club()->id,
            'amount' => (int) $order->total_price,
            'reason' => self::TILL_REASON,
            'payable_type' => BarOrder::class,
            'payable_id' => $order->id,
            'recorded_by_id' => auth()->id() ?? $order->created_by ?? $recordedBy,
            'notes' => $order->name,
        ]);
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
    public function reserve(BarOrder $order): ?Payment
    {
        return DB::transaction(function () use ($order): ?Payment {
            $order = BarOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->is_paid) {
                return null;
            }

            $payment = $order->payment()->first();

            if ($payment === null) {
                return $this->createPayment($order);
            }

            $payment->update(['amount_due' => $order->total_price / 100]);

            return $payment;
        });
    }

    private function createPayment(BarOrder $order): Payment
    {
        return $order->payment()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $order->total_price / 100,
            'amount_paid' => 0,
            'status' => 'pending',
            'payment_method' => self::TREASURY_METHOD,
        ]);
    }
}
