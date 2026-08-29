<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\TournamentStatusEnum;

class TournamentPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::TournamentsManage->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Tournament $tournament): bool
    {
        return $user->can(Permission::TournamentsManage->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Tournament $tournament): bool
    {
        return $user->can(Permission::TournamentsManage->value) && $tournament->status !== TournamentStatusEnum::PENDING;
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
        return $user->can(Permission::TournamentsManage->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updatesBeforeStart(User $user, Tournament $tournament): bool
    {
        return $user->can(Permission::TournamentsManage->value) && $tournament->registrationsAreOpen();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updateSubscriptionAsUser(User $user, Tournament $tournament): bool
    {
        return $tournament->registrationsAreOpen();
    }

    /**
     * Determine whether the user can view the model.
     */
    /**
     * Denies everyone, and viewAny() allows everyone — the two contradict each
     * other. Both are left as they were: no call site exercises view(), a test
     * documents each, and reconciling them is a product decision rather than a
     * side effect of naming the rights.
     */
    public function view(User $user, Tournament $tournament): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view any models.
     */
    /**
     * Open on purpose: members browse the tournament list to register for one.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }
}
