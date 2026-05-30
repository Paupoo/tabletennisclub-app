<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Models\ClubAdmin\Users\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingActionItem>
 */
class MeetingActionItemFactory extends Factory
{
    protected $model = MeetingActionItem::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'title' => fake()->sentence(5),
            'description' => fake()->optional()->paragraph(),
            'assigned_to_id' => fake()->boolean(60) ? User::factory() : null,
            'due_date' => fake()->optional()->dateTimeBetween('now', '+3 months'),
            'is_completed' => fake()->boolean(20),
        ];
    }
}
