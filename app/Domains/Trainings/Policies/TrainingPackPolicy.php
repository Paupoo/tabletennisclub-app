<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * Every method used to `return false` — a stub that was never wired to anything,
 * so the training packs screen carried its own rules inline. Filled in here so
 * the ability has one home; no behaviour changes, since nothing called it.
 */
class TrainingPackPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    public function delete(User $user, TrainingPack $trainingPack): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * A pack carries enrolments and payments; it is discontinued, never erased.
     */
    public function forceDelete(User $user, TrainingPack $trainingPack): bool
    {
        return $user->hasRole(Role::ADMINISTRATOR->value);
    }

    public function restore(User $user, TrainingPack $trainingPack): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    public function update(User $user, TrainingPack $trainingPack): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * The trainer running the pack reads it too, without holding the duty of
     * building the season's offer.
     */
    public function view(User $user, TrainingPack $trainingPack): bool
    {
        return $user->can(Permission::TrainingsView->value)
            || $trainingPack->trainer_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TrainingsView->value);
    }
}
