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
        if ($user->is_competitor && $user->wasChanged('ranking')) {
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
