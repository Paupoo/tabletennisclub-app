<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingAgendaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAgendaItem>
 */
class MeetingAgendaItemFactory extends Factory
{
    protected $model = MeetingAgendaItem::class;

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
