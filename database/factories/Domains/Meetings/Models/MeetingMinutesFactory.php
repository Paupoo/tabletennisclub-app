<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingMinutes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingMinutes>
 */
class MeetingMinutesFactory extends Factory
{
    protected $model = MeetingMinutes::class;

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
