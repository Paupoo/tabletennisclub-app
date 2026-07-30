<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\Permission;

class TeamPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::TeamsManage->value);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->can(Permission::TeamsManage->value);
    }

    public function forceDelete(User $user, Team $team): bool
    {
        return false;
    }

    public function restore(User $user, Team $team): bool
    {
        return false;
    }

    public function update(User $user, ?Team $team = null): bool
    {
        return $user->can(Permission::TeamsManage->value);
    }

    public function view(User $user, Team $team): bool
    {
        return $user->can(Permission::InterclubsView->value);
    }

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InterclubsView->value);
    }
}
