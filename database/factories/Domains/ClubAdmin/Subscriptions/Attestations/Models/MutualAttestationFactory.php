<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Subscriptions\Attestations\Models;

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Shared\Enums\Mutuality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MutualAttestation>
 */
class MutualAttestationFactory extends Factory
{
    protected $model = MutualAttestation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Built from one affiliation rather than three loose foreign keys: a
        // certificate that named a member who never affiliated would pass every
        // test and describe nothing.
        $affiliation = Subscription::factory()->create();

        return [
            'user_id' => $affiliation->user_id,
            'subscription_id' => $affiliation->id,
            'season_id' => $affiliation->season_id,
            'mutuality' => $this->faker->randomElement(Mutuality::cases())->value,
            'reference' => 'ATT-2627-' . $this->faker->unique()->numerify('#####'),
            'token' => strtolower((string) Str::ulid()),
            'path' => null,
            'amount_certified' => 125,
            'period_from' => now()->subMonths(2),
            'period_to' => now()->addMonths(8),
            'signatory_name' => $this->faker->name(),
            'discipline' => 'Tennis de table',
            'issued_at' => now()->subMonths(2),
        ];
    }

    /** Already withdrawn: the season is free for a new one. */
    public function revoked(string $reason = 'Mutuelle erronée'): static
    {
        return $this->state(fn (array $attributes): array => [
            'revoked_at' => now(),
            'revocation_reason' => $reason,
        ]);
    }
}
