<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Meeting;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Meeting\Meeting;
use App\Models\ClubEvents\Meeting\MeetingMinutes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingMinutes>
 */
class MeetingMinutesFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'announcements' => null,
            'decisions' => null,
            'notes' => null,
            'is_published' => false,
            'published_at' => null,
            'published_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'is_published' => true,
            'published_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'published_by' => User::factory(),
        ]);
    }
}
