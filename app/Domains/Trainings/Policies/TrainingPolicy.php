<?php

declare(strict_types=1);

namespace App\Domains\Trainings\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Trainings\Models\Training;

class TrainingPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Training $training): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Training $training): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * Qui peut pointer, corriger ou annuler *cette* séance.
     *
     * `coach_area.access` n'ouvre que l'écran : il ne dit rien de la séance
     * qu'on y manipule. Sans cette distinction, n'importe quel coach pouvait
     * pointer — et annuler, avec les mails aux inscrits — la séance d'un
     * collègue en appelant la méthode Livewire directement.
     *
     * La délégation garde la main partout : elle doit pouvoir couvrir un
     * remplacement improvisé et corriger un pointage après coup.
     */
    public function recordAttendance(User $user, Training $training): bool
    {
        return $user->can(Permission::TrainingsManage->value)
            || $training->trainer_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Training $training): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Training $training): bool
    {
        return $user->can(Permission::TrainingsManage->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Training $training): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }
}
