<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;

class UnconfirmSubscriptionAction
{
    public function __invoke(Subscription $subscription): RedirectResponse
    {
        try {
            $subscription->unconfirm();
        } catch (\Throwable $th) {
            return back()
                ->withErrors(['error' => $th->getMessage()]);
        }

        $subscription->fresh();

        return back()
            ->with([
                'success' => __('The subscription has been set to pending'),
            ]);
    }
}
