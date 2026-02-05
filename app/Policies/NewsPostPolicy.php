<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubPosts\NewsPost;

class NewsPostPolicy
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
    public function delete(User $user, NewsPost $newsPost): bool
    {
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function view(User $user, NewsPost $article): bool
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
        return $user->is_admin || $user->is_committee_member;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function forceDelete(User $user, NewsPost $article): bool
    {
        return false;
    }
}
