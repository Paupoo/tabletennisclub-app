<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Users\Models;

use App\Domains\ClubAdmin\Users\Models\FamilyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyGroup>
 */
class FamilyGroupFactory extends Factory
{
    protected $model = FamilyGroup::class;

    public function definition(): array
    {
        return [];
    }
}
