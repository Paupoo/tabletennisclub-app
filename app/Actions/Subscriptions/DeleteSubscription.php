<?php

namespace App\Actions\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeleteSubscription extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()
            ->withInput(
                ['success' => __('The subscription has been deleted'),]
            );
    }
}
