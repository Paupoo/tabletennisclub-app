<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\User;
use Illuminate\Http\Request;

class UnsubscribeFromSeasonAction
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Season $season, User $user)
    {
        $season->users()->detach($user);

        return back()->with([
            'success' => __('The user has been unsuscribed successfully'),
        ]);
    }
}
