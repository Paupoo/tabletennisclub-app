<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;

class ConfirmSubscriptionAction
{
    public function __invoke(Subscription $subscription): RedirectResponse
    {
        try {
            new CalculatePriceAction()($subscription);
            $subscription->confirm();
        } catch (\Throwable $th) {
            return back()
                ->withErrors(['errror' => $th->getMessage()]);
        }

        return back()
            ->with([
                'success' => __('The subscription has been confirmed'),
            ]);
    }
}
