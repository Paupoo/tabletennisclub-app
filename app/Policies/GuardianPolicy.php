<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Support\AccountProxy;

class GuardianPolicy
{
    public function create(User $user, ?User $target = null): bool
    {
        if ($user->can(Permission::UsersUpdate->value)) {
            return true;
        }

        return $target !== null && $user->is($target);
    }

    public function delete(User $user, Guardian $guardian): bool
    {
        return $user->can(Permission::UsersUpdate->value);
    }

    public function forceDelete(User $user, Guardian $guardian): bool
    {
        return false;
    }

    /** Folding a sheet into the person already on file is the office's call. */
    public function merge(User $user, Guardian $guardian): bool
    {
        return ! $guardian->hasAccount() && $user->can(Permission::UsersUpdate->value);
    }

    public function restore(User $user, Guardian $guardian): bool
    {
        return false;
    }

    /**
     * A guardian who holds an account keeps their details on it and corrects
     * them in their own profile: the sheet is only the link to their wards.
     *
     * Besides the office, the member a guardian answers for may correct them —
     * they are the one who notices the old number. Not under a proxy, though:
     * one parent would then rewrite the other's details in their child's name.
     */
    public function update(User $user, Guardian $guardian): bool
    {
        if ($guardian->hasAccount()) {
            return false;
        }

        if ($user->can(Permission::UsersUpdate->value)) {
            return true;
        }

        return ! AccountProxy::isActing()
            && $user->guardians()->whereKey($guardian->id)->exists();
    }

    public function view(User $user, Guardian $guardian): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }
}
