<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only allow admin & committee mebers to create new users
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        //
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can delete the model.
     */public function selfDelete(User $user, User $model): bool
    {
        //
        return $user->is($model);
    }

    /**
     * Determine wether the user can delete force index.
     */
    public function deleteForceList(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        //
        return false;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        //
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        //
        return false;
    }

    public function sendEmail(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine wether the user can set or update force index.
     */
    public function setOrUpdateForceList(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        //
        return $user->is_admin || $user->is_committee_member;
    }

    public function updatePassword(User $user, User $model): bool
    {
        //
        return $user->is_admin || $user->is_committee_member || $user->is($model);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        //
        return true;
    }
}
