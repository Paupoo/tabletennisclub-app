<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Meeting;

use App\Models\ClubEvents\Meeting\Meeting;
use App\Models\ClubEvents\Meeting\MeetingDateProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingDateProposal>
 */
class MeetingDateProposalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'proposed_at' => fake()->dateTimeBetween('+1 week', '+6 weeks'),
            'is_selected' => false,
        ];
    }
}
