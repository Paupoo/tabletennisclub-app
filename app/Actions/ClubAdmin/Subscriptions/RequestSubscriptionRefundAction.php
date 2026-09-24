<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Subscriptions\Notifications\SubscriptionRefundRequestedNotification;

class RequestSubscriptionRefundAction
{
    /**
     * Create a `to_refund` payment picked up by the treasury reconciliation
     * workflow (weekly reminders, bank matching) and notify the treasurer & secretary.
     *
     * @param  string  $reason  Already-translated context line for the treasurer email; empty = generic wording.
     * @param  string|null  $targetIban  Le compte à rembourser, quand ce n'est pas celui du membre.
     */
    public function __invoke(
        Subscription $subscription,
        float $amount,
        string $reason = '',
        ?string $targetIban = null,
    ): Payment {
        $payment = $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            // Le compte qui a versé quand on le connaît, celui du membre sinon.
            // Un trop-perçu se rend d'où il vient ; une cotisation annulée se
            // rend à son titulaire.
            'refund_iban' => $targetIban ?? $subscription->user?->iban,
            'amount_due' => $amount,
            // Rien n'est sorti tant qu'aucun débit n'apparaît sur le relevé :
            // `amount_due` porte l'engagement, `amount_paid` reste le miroir de
            // ce qui a réellement quitté le compte. Les deux portaient le même
            // chiffre, et « remboursement promis » ne se distinguait plus de
            // « remboursement versé » autrement que par le statut.
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);

        User::permission(Permission::PaymentsRefund->value)
            ->get()
            ->each->notify(new SubscriptionRefundRequestedNotification($payment, $subscription, $reason));

        return $payment;
    }
}
