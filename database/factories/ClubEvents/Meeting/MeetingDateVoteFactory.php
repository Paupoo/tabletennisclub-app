<?php

declare(strict_types=1);

namespace Database\Factories\ClubEvents\Meeting;

use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Meeting\MeetingDateProposal;
use App\Models\ClubEvents\Meeting\MeetingDateVote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingDateVote>
 */
class MeetingDateVoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_date_proposal_id' => MeetingDateProposal::factory(),
            'user_id' => User::factory(),
            'vote' => fake()->randomElement(MeetingDateVoteEnum::cases())->value,
        ];
    }
}
