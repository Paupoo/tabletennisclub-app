<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Team;

class TeamPolicy
{
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function forceDelete(User $user, Team $team): bool
    {
        return false;
    }

    public function restore(User $user, Team $team): bool
    {
        return false;
    }

    public function update(User $user, ?Team $team = null): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function view(User $user, Team $team): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
