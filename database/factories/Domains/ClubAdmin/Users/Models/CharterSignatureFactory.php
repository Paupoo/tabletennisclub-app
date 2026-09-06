<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Users\Models;

use App\Domains\ClubAdmin\Users\Models\CharterSignature;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Support\ClubCharter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharterSignature>
 */
class CharterSignatureFactory extends Factory
{
    protected $model = CharterSignature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'season_id' => Season::factory(),
            'signed_by_user_id' => $user,
            'version' => ClubCharter::VERSION,
            'signed_at' => now(),
        ];
    }

    /**
     * A guardian signing for someone else in their family group.
     */
    public function signedBy(User $guardian): static
    {
        return $this->state(fn (array $attributes): array => [
            'signed_by_user_id' => $guardian->id,
        ]);
    }
}
