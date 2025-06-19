<?php

declare(strict_types=1);

namespace Tests\Trait;

use App\Models\User;

trait CreateUser
{
    public function createFakeAdmin(): User
    {
        return User::factory()->isAdmin()->create();
    }

    public function createFakeCommitteeMember(): User
    {
        return User::factory()->isCommitteeMember()->create();
    }

    public function createFakeUser(): User
    {
        return User::factory()->create();
    }
}
