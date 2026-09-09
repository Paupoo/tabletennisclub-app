<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domains\Competitions\Interclub\Models\Team;

class TeamObserver
{
    /**
     * Empty the roster through Eloquent before the team goes.
     *
     * The team_user foreign keys cascade on delete, so the database would
     * otherwise remove the rows itself, silently: no model event, no audit
     * entry, and no way to tell afterwards who was in the team that was just
     * deleted. Two of the three delete paths detached by hand; the third did
     * not, and nothing stopped a fourth from forgetting too.
     *
     * detach() is idempotent, so the hand-written calls that remain elsewhere
     * simply find nothing left to remove.
     */
    public function deleting(Team $team): void
    {
        $team->users()->detach();
    }
}
