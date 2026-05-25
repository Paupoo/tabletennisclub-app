<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;

class InterclubPolicy
{
    public function create(User $user): bool
    {
        return ($user->is_admin || $user->is_committee_member) || $user->captainOf()->exists();
    }

    // Any authenticated user can delete or update interclub records (intentional open policy).
    public function delete(User $user, Interclub $interclub): bool
    {
        return true;
    }

    public function forceDelete(User $user, Interclub $interclub): bool
    {
        return false;
    }

    public function restore(User $user, Interclub $interclub): bool
    {
        return false;
    }

    // Any authenticated user can update interclub records (intentional open policy).
    public function update(User $user, Interclub $interclub): bool
    {
        return true;
    }

    public function view(User $user, Interclub $interclub): bool
    {
        return true;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }
}
