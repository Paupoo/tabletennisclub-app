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
     */
    public function __invoke(Subscription $subscription, float $amount, string $reason = ''): Payment
    {
        $payment = $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $amount,
            'amount_paid' => $amount,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);

        User::permission(Permission::PaymentsRefund->value)
            ->get()
            ->each->notify(new SubscriptionRefundRequestedNotification($payment, $subscription, $reason));

        return $payment;
    }
}
