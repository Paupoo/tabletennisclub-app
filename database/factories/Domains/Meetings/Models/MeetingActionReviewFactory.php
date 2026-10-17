<?php

declare(strict_types=1);

namespace Database\Factories\Domains\Meetings\Models;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Models\MeetingActionItem;
use App\Domains\Meetings\Models\MeetingActionReview;
use App\Domains\Shared\Enums\MeetingActionReviewOutcomeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingActionReview>
 */
class MeetingActionReviewFactory extends Factory
{
    protected $model = MeetingActionReview::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'action_item_id' => MeetingActionItem::factory(),
            'outcome' => MeetingActionReviewOutcomeEnum::IN_PROGRESS,
        ];
    }
}
