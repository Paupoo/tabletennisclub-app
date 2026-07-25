<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Subscriptions\Notifications\SubscriptionCancelledNotification;

class CancelSubscriptionWithRefundAction
{
    /**
     * Cancel a subscription request, optionally issuing a refund of money already collected.
     *
     * - Releases every training pack (freeing spots and promoting the waitlist).
     *   A validated pack is dated out rather than deleted, so the months already
     *   attended stay billed: the caller decides the refund, and the treasury
     *   screen proposes it net of what was consumed
     *   ({@see CalculatePriceAction::consumedTrainingTotal()}).
     * - Marks outstanding pending payments as cancelled so they leave the treasury workflow.
     * - With a refund amount: creates a dedicated `to_refund` payment picked up by the
     *   treasury reconciliation workflow, transitions the subscription to `refunded`,
     *   and notifies the treasurer & secretary.
     * - Without: transitions the subscription to `cancelled`.
     * - Always notifies the member.
     *
     * @return Payment|null The refund payment when money is to be returned, null otherwise.
     */
    public function __invoke(Subscription $subscription, float $refundAmount = 0.0, string $message = ''): ?Payment
    {
        $totalPaid = $subscription->totalPaid();

        if ($refundAmount < 0 || $refundAmount > $totalPaid) {
            throw new \InvalidArgumentException(__('The refund amount must be between 0 and the total paid (:total €).', [
                'total' => number_format($totalPaid, 2),
            ]));
        }

        $familyMembersCount = $subscription->has_other_family_members ? 2 : 1;
        foreach ($subscription->trainingPacks()->get() as $pack) {
            (new LeaveTrainingPackAction)($subscription, $pack, $familyMembersCount, notifyUser: false);
        }

        $subscription->payments()->where('status', 'pending')->update(['status' => 'cancelled']);

        $refundPayment = null;

        if ($refundAmount > 0) {
            $refundPayment = (new RequestSubscriptionRefundAction)($subscription, $refundAmount);
            $subscription->refund();
        } else {
            $subscription->cancel();
        }

        $subscription->user->notify(new SubscriptionCancelledNotification($subscription, $refundAmount, $message));

        return $refundPayment;
    }
}
