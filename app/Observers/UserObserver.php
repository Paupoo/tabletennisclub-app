<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\User\RecalculateForceListAction;
use App\Domains\ClubAdmin\Users\Models\User;

class UserObserver
{
    public function deleted(User $user): void
    {
        if ($user->is_competitor) {
            RecalculateForceListAction::handle();
        }
    }

    public function saved(User $user): void
    {
        // ranking drives the general list; gender and birthdate move a member
        // on/off the women's and veterans' sub-lists.
        if ($user->is_competitor && $user->wasChanged(['ranking', 'gender', 'birthdate'])) {
            RecalculateForceListAction::handle();
        }
    }

    /*
     * The "a non committee member holds no statutory title" rule used to live here,
     * in saving(). It cannot anymore: `is_committee_member` now resolves against the
     * roles, and roles only exist once the row does — so on creation the observer
     * would read "not a committee member" and wipe the title being set.
     *
     * It moved to SyncUserRolesAction, which runs right after the roles are synced
     * and is therefore the first point where the answer is knowable.
     */
}
