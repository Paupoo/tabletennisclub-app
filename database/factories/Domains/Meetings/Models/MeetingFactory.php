<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\Shared\Enums\MeetingFormatEnum;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Models\ClubAdmin\Users\User;
use App\Domains\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function committee(): static
    {
        return $this->state([
            'type' => MeetingTypeEnum::COMMITTEE,
            'is_public' => false,
        ]);
    }

    public function completed(): static
    {
        $scheduledAt = fake()->dateTimeBetween('-6 months', '-1 week');

        return $this->state([
            'status' => MeetingStatusEnum::COMPLETED,
            'scheduled_at' => $scheduledAt,
            'ends_at' => (clone $scheduledAt)->modify('+2 hours'),
        ]);
    }

    public function confirmed(): static
    {
        $scheduledAt = fake()->dateTimeBetween('+1 week', '+3 months');

        return $this->state([
            'status' => MeetingStatusEnum::CONFIRMED,
            'scheduled_at' => $scheduledAt,
            'ends_at' => (clone $scheduledAt)->modify('+2 hours'),
        ]);
    }

    public function definition(): array
    {
        $type = fake()->randomElement(MeetingTypeEnum::cases());
        $status = fake()->randomElement(MeetingStatusEnum::cases());
        $format = fake()->randomElement(MeetingFormatEnum::cases());

        $scheduledAt = match ($status) {
            MeetingStatusEnum::PLANNING => null,
            MeetingStatusEnum::COMPLETED => fake()->dateTimeBetween('-6 months', '-1 week'),
            default => fake()->dateTimeBetween('+1 week', '+3 months'),
        };

        return [
            'title' => fake()->sentence(4),
            'type' => $type,
            'status' => $status,
            'format' => $format,
            'is_public' => $type === MeetingTypeEnum::GENERAL_ASSEMBLY,
            'description' => fake()->optional()->paragraph(),
            'scheduled_at' => $scheduledAt,
            'ends_at' => $scheduledAt ? (clone $scheduledAt)->modify('+2 hours') : null,
            'location' => $format === MeetingFormatEnum::PHYSICAL ? fake()->address() : null,
            'meeting_link' => $format === MeetingFormatEnum::VIRTUAL ? fake()->url() : null,
            'rsvp_deadline' => $scheduledAt ? (clone $scheduledAt)->modify('-3 days') : null,
            'has_meal' => false,
            'meal_description' => null,
            'meal_price_cents' => null,
            'quorum' => null,
            'created_by' => User::factory(),
        ];
    }

    public function generalAssembly(): static
    {
        return $this->state([
            'type' => MeetingTypeEnum::GENERAL_ASSEMBLY,
            'is_public' => true,
        ]);
    }

    public function physical(): static
    {
        return $this->state([
            'format' => MeetingFormatEnum::PHYSICAL,
            'location' => fake()->address(),
            'meeting_link' => null,
        ]);
    }

    public function planning(): static
    {
        return $this->state([
            'status' => MeetingStatusEnum::PLANNING,
            'scheduled_at' => null,
            'ends_at' => null,
        ]);
    }

    public function virtual(): static
    {
        return $this->state([
            'format' => MeetingFormatEnum::VIRTUAL,
            'location' => null,
            'meeting_link' => 'https://meet.google.com/' . fake()->bothify('???-????-???'),
        ]);
    }

    public function withMeal(string $description = 'Pizza party', int $priceCents = 1000): static
    {
        return $this->state([
            'has_meal' => true,
            'meal_description' => $description,
            'meal_price_cents' => $priceCents,
        ]);
    }

    public function withQuorum(int $quorum = 10): static
    {
        return $this->state(['quorum' => $quorum]);
    }
}
