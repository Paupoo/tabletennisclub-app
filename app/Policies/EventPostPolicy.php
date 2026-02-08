<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubPosts\EventPost;

class EventPostPolicy
{
    public function archive(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

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
    public function delete(User $user, EventPost $event): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function duplicate(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EventPost $event): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    public function publish(User $user): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EventPost $event): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EventPost $event): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EventPost $event): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }
}
