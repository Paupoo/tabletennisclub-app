<?php

namespace App\Actions\Subscriptions;


use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;

class ConfirmSubscriptionAction
{
   public function __invoke(Subscription $subscription): RedirectResponse
   {
        try {
            $subscription->confirm();
        } catch (\Throwable $th) {
            return back()
                ->withErrors(['errror' => $th->getMessage()]);
        }

        return back()
            ->withInput([
                'success' => __('The subscription has been confirmed'),
            ]);
   }
}
