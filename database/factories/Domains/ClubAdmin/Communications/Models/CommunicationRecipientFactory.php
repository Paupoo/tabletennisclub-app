<?php

declare(strict_types=1);

namespace Database\Factories\Domains\ClubAdmin\Communications\Models;

use App\Domains\ClubAdmin\Communications\Models\Communication;
use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunicationRecipient>
 */
class CommunicationRecipientFactory extends Factory
{
    protected $model = CommunicationRecipient::class;

    public function definition(): array
    {
        return [
            'communication_id' => Communication::factory(),
            'email' => fake()->unique()->safeEmail(),
            'user_ids' => [],
            'status' => CommunicationRecipient::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommunicationRecipient::STATUS_FAILED,
            'error' => 'Connection refused',
            'sent_at' => null,
        ]);
    }
}
