<?php

namespace App\Actions\Subscriptions;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\User;
use Illuminate\Http\Request;

class UnsubscribeFromSeasonController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Season $season, User $user)
    {
        $season->users()->detach($user);

        return back()->withInput([
            'success' => __('The user has been suscribed successfully'),
        ]);
    }
}
