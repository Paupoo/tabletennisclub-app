<?php

declare(strict_types=1);

namespace App\Console\Commands\Feedback;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Notifications\CampaignSummaryNotification;
use App\Domains\ClubAdmin\Feedback\Services\CampaignAudience;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use App\Jobs\SendCampaignInvitationJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * The three mailings of a scheduled survey, each sent once: the invitation on
 * the opening day, a single reminder halfway to those who have not answered,
 * and the summary to the committee the day after the close. Each is stamped
 * on the campaign, so running the command twice sends nothing twice.
 */
#[Signature('feedback:send-campaign-mailings')]
#[Description('Send the yearly survey invitation, its one reminder, and the summary to the committee')]
class SendCampaignMailingsCommand extends Command
{
    public function handle(CampaignAudience $audience): int
    {
        FeedbackCampaign::query()->scheduled()->orderBy('opens_on')->each(function (FeedbackCampaign $campaign) use ($audience): void {
            if ($campaign->isOpen() && $campaign->invited_at === null) {
                $this->fanOut($campaign, $audience, $audience->members(), isReminder: false);
                $campaign->update(['invited_at' => now()]);

                return;
            }

            if ($campaign->isOpen() && $campaign->reminded_at === null && today()->gte($campaign->reminderDay())) {
                $answered = $campaign->participants()->pluck('users.id')->all();
                $waiting = $audience->members()->reject(fn (User $member): bool => in_array($member->id, $answered, true));

                $this->fanOut($campaign, $audience, $waiting, isReminder: true);
                $campaign->update(['reminded_at' => now()]);

                return;
            }

            if ($campaign->closes_on->lt(today()) && $campaign->invited_at !== null && $campaign->summarised_at === null) {
                Notification::send(User::role(Role::COMMITTEE->value)->get(), new CampaignSummaryNotification($campaign));
                $campaign->update(['summarised_at' => now()]);
            }
        });

        return self::SUCCESS;
    }

    /**
     * @param  iterable<int, User>  $members
     */
    private function fanOut(FeedbackCampaign $campaign, CampaignAudience $audience, iterable $members, bool $isReminder): void
    {
        foreach ($audience->byAddress($members) as $address => $group) {
            SendCampaignInvitationJob::dispatch(
                $campaign->id,
                $address,
                $group->map(fn (User $member): string => $member->first_name)->values()->all(),
                $isReminder,
            );
        }
    }
}
