<?php

declare(strict_types=1);

namespace App\Domains\ClubPosts\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Shared\Enums\Permission;

class EventPostPolicy
{
    public function archive(User $user): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EventPost $event): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    public function duplicate(User $user): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EventPost $event): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    public function publish(User $user): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EventPost $event): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EventPost $event): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EventPost $event): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }

    /**
     * Determine whether the user can view any models.
     */
    /**
     * Was `false` while every sibling allowed the committee — a stub, never called.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::EventPostsManage->value);
    }
}
