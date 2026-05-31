<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\MeetingDateProposal;
use App\Domains\Meetings\Models\MeetingDateVote;
use App\Domains\Shared\Enums\MeetingDateVoteEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingDateVote>
 */
class MeetingDateVoteFactory extends Factory
{
    protected $model = MeetingDateVote::class;

    public function definition(): array
    {
        return [
            'meeting_date_proposal_id' => MeetingDateProposal::factory(),
            'user_id' => User::factory(),
            'vote' => fake()->randomElement(MeetingDateVoteEnum::cases())->value,
        ];
    }
}
