<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Models\ClubAdmin\Subscription\Subscription;
use Illuminate\Http\RedirectResponse;

class MarkRefundSubscriptionAction
{
    /**
     * @param Subscription $subscription
     * @return RedirectResponse
     */
    public function __invoke(Subscription $subscription): RedirectResponse
    {
        try {
            $subscription->refund();
        } catch (\Throwable $th) {
            return back()
                ->withErrors(['error' => $th->getMessage()]);
        }

        $subscription->fresh();

        return back()
            ->with([
                'success' => __('The subscription has been marked as refunded.'),
            ]);
    }
}
