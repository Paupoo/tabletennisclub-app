<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Tournament;

use App\Actions\Tournament\ToggleHasPaidTournamentAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\RedirectResponse;

class ToggleHasPaidController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @throws Exception
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
