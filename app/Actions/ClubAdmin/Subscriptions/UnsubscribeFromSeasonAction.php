<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use Illuminate\Http\Request;

class UnsubscribeFromSeasonAction
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Season $season, User $user)
    {
        $subscription = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->first();

        if (! $subscription) {
            return back()->withErrors([
                'error' => __('No active subscription found for this season'),
            ]);
        }

        try {
            $subscription->cancel();
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => $th->getMessage()]);
        }

        return back()->with([
            'success' => __('The user has been unsuscribed successfully'),
        ]);
    }
}
