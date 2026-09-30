<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingDecision>
 */
class MeetingDecisionFactory extends Factory
{
    protected $model = MeetingDecision::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'agenda_item_id' => null,
            'body' => fake()->sentence(8),
            'sort_order' => 0,
        ];
    }
}
