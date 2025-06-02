<?php

declare(strict_types=1);

namespace Tests\Trait;

use App\Models\User;

trait CreateUser
{
    public function createFakeAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
        ]);
    }

    public function createFakeComitteeMember(): User
    {
        return User::factory()->create([
            'is_comittee_member' => true,
        ]);
    }

    public function createFakeUser(): User
    {
        return User::factory()->create();
    }
}
