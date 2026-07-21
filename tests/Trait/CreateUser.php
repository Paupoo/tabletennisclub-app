<?php

declare(strict_types=1);

namespace Tests\Trait;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

trait CreateUser
{
    public function createFakeAdmin(): User
    {
        return User::factory()->isAdmin()->create();
    }

    /**
     * A committee member who also runs member administration — which is what the
     * tests using this helper actually mean by "committee member".
     */
    public function createFakeCommitteeMember(): User
    {
        return User::factory()->isCommitteeMember()->withRole(Role::MEMBERS)->create();
    }

    /** On the committee, holding no operational duty. */
    public function createFakeCommitteeMemberWithoutDelegation(): User
    {
        return User::factory()->isCommitteeMember()->create();
    }

    public function createFakeUser(): User
    {
        return User::factory()->create();
    }
}
