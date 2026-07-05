<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;

class GuardianPolicy
{
    public function create(User $user, ?User $target = null): bool
    {
        if ($user->is_admin || $user->is_committee_member) {
            return true;
        }

        return $target !== null && $user->is($target);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function forceDelete(User $user, Guardian $guardian): bool
    {
        return false;
    }

    public function restore(User $user, Guardian $guardian): bool
    {
        return false;
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function viewAny(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }
}
