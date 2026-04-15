<?php

namespace App\Observers;

use App\Models\ClubAdmin\Users\User;

class UserObserver
{

    public function saving(User $user): void
    {
        if (!$user->is_committee_member) {
            // clean committe role.
            $user->committee_role = null;
        }
    }
}
