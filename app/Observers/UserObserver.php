<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\User\RecalculateForceListAction;
use App\Models\ClubAdmin\Users\User;

class UserObserver
{
    public function saved(User $user): void
    {
        $competitorStatusChanged = $user->wasChanged('is_competitor');
        $rankingChangedForCompetitor = $user->is_competitor && $user->wasChanged('ranking');

        if ($competitorStatusChanged || $rankingChangedForCompetitor) {
            RecalculateForceListAction::handle();
        }
    }

    public function saving(User $user): void
    {
        if (! $user->is_committee_member) {
            // clean committe role.
            $user->committee_role = null;
        }
    }
}
