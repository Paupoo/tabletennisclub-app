<?php

namespace Tests\Trait;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait CreateUser
{
    
    public function createFakeMember(): User
    {
        return User::factory()->create();
    }

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
}
