<?php

namespace App\Policies;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function index(User $user): bool
    {
        //
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        //
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only allow admin & comittee mebers to create new users
        return $user->is_admin || $user->is_comittee_member;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user): bool
    {
        //
        return $user->is_admin || $user->is_comittee_member;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user): bool
    {
        //
        return $user->is_admin || $user->is_comittee_member;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        //
    }

    /**
     * Determine wether the user can set or update force index.
     */
    public function setOrUpdateForceIndex(User $user): bool
    {
        return $user->is_admin || $user->is_comittee_member;
    }

     /**
     * Determine wether the user can delete force index.
     */
    public function deleteForceIndex(User $user): bool
    {
        return $user->is_admin || $user->is_comittee_member;
    }
}
