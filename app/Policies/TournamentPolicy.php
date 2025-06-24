<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\TournamentStatusEnum;
use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
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
    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tournament $tournament): bool
    {
        return ($user->is_admin || $user->is_committee_member) && $tournament->status !== TournamentStatusEnum::PENDING;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Tournament $tournament): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updatesBeforeStart(User $user, Tournament $tournament): bool
    {
        return ($user->is_admin || $user->is_committee_member) && $tournament->status->value === TournamentStatusEnum::PUBLISHED->value;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updateSubscriptionAsUser(User $user, Tournament $tournament): bool
    {
        return $tournament->status->value === TournamentStatusEnum::PUBLISHED->value;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Tournament $tournament): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }
}
