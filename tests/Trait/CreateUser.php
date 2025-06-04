<?php

declare(strict_types=1);

namespace Tests\Trait;

use App\Models\User;

trait CreateUser
{

    private array $licencesToExclude = [
            223344,
            123123,
            112233,
            443211,
            987654,
            332211,
            154856,
            852364,
            124599,
            111952,
            123456,
        ];

    public function createFakeAdmin(): User
    {
        return User::factory()->create([
            'is_admin' => true,
            'licence' => fake()->unique()->numberBetweenNot(95000, 170000, $this->licencesToExclude),
        ]);
    }

    public function createFakeComitteeMember(): User
    {
        return User::factory()->create([
            'is_comittee_member' => true,
            'licence' => fake()->unique()->numberBetweenNot(95000, 170000, $this->licencesToExclude),
        ]);
    }

    public function createFakeUser(): User
    {
        return User::factory()->create([
            'licence' => fake()->unique()->numberBetweenNot(95000, 170000, $this->licencesToExclude),
        ]);
    }
}
