<?php

declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;

class MarkPaidSubscriptionAction
{
    public function __invoke(Subscription $subscription): RedirectResponse
    {
        try {
            $subscription->markAsPaid();
        } catch (\Throwable $th) {
            return back()
                ->withErrors(['error' => $th->getMessage()]);
        }

        $subscription->fresh();

        return back()
            ->withInput([
                'success' => __('The subscription has been marked as paid'),
            ]);
    }
}
