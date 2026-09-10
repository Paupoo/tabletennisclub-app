<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Club\Models;

use App\Domains\ClubAdmin\Club\Models\KeyRing;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KeyRing>
 */
class KeyRingFactory extends Factory
{
    protected $model = KeyRing::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'held_by_user_id' => null,
            'notes' => null,
        ];
    }

    /**
     * A ring in someone's pocket rather than in the drawer.
     */
    public function heldBy(User $user): static
    {
        return $this->state(fn (): array => ['held_by_user_id' => $user->id]);
    }

    /**
     * A ring already taken out of service.
     */
    public function retired(): static
    {
        return $this->state(fn (): array => ['deleted_at' => now()]);
    }
}
