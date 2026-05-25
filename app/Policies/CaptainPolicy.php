<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Captain;
use App\Models\ClubAdmin\Users\User;

class CaptainPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, Captain $captain): bool
    {
        return false;
    }

    public function forceDelete(User $user, Captain $captain): bool
    {
        return false;
    }

    public function restore(User $user, Captain $captain): bool
    {
        return false;
    }

    public function update(User $user, Captain $captain): bool
    {
        return false;
    }

    public function view(User $user, Captain $captain): bool
    {
        return false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }
}
