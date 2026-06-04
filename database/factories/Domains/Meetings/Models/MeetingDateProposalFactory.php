<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingDateProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingDateProposal>
 */
class MeetingDateProposalFactory extends Factory
{
    protected $model = MeetingDateProposal::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'proposed_at' => fake()->dateTimeBetween('+1 week', '+6 weeks'),
            'is_selected' => false,
        ];
    }
}
