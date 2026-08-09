<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;

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

    public function restore(User $user, Guardian $guardian): bool
    {
        return false;
    }

    public function update(User $user, Guardian $guardian): bool
    {
        return $user->can(Permission::UsersUpdate->value);
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
