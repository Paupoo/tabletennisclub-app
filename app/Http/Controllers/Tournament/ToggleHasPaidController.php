<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tournament;

use App\Actions\Tournament\ToggleHasPaidTournamentAction;
use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ToggleHasPaidController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Tournament $tournament, User $user): RedirectResponse
    {
        $this->authorize('updatesBeforeStart', $tournament);
        $action = new ToggleHasPaidTournamentAction($user);
        $action->toggleHasPaid($tournament);

        return redirect()
            ->back();
    }
}
