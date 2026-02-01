<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Subscription\Subscription;
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
                ['success' => __('The subscription has been deleted')]
            );
    }
}
