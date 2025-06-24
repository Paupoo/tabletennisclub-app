<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user = null, ?Team $team = null): bool
    {
        //
        return $user->is_admin || $user->is_comitte_member;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Team $team): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user = null, ?Team $team = null): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool {
        return true;
    }
}
