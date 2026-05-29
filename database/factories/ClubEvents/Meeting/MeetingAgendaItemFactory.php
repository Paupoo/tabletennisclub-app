<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Meeting;

use App\Models\ClubEvents\Meeting\Meeting;
use App\Models\ClubEvents\Meeting\MeetingAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAgendaItem>
 */
class MeetingAgendaItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'sort_order' => fake()->numberBetween(0, 10),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
