<?php

declare(strict_types=1);

namespace App\Domains\ClubPosts\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Shared\Enums\Permission;

class NewsPostPolicy
{
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::NewsPostsManage->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NewsPost $newsPost): bool
    {
        return $user->can(Permission::NewsPostsManage->value);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function forceDelete(User $user, NewsPost $article): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, NewsPost $newsPost): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NewsPost $article): bool
    {
        return $user->can(Permission::NewsPostsManage->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    /**
     * Back-office reading of an article, drafts included — the public site does
     * not go through the policy. Was a `return false` stub with no call site.
     */
    public function view(User $user, NewsPost $article): bool
    {
        return $user->can(Permission::NewsPostsView->value);
    }
}
