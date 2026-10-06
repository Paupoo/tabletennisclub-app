<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Feedback\Models\FeedbackCampaign;
use App\Domains\ClubAdmin\Feedback\Notifications\CampaignInvitationNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One address's survey invitation or reminder.
 *
 * Shares the `invitations` limiter of {@see AppServiceProvider}: a bulk,
 * member-facing mailing nobody waits on, like the tournament announcements.
 */
class SendCampaignInvitationJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    /**
     * @param  array<int, string>  $names
     */
    public function __construct(public int $campaignId, public string $address, public array $names, public bool $isReminder = false) {}

    public function handle(): void
    {
        $campaign = FeedbackCampaign::find($this->campaignId);

        if ($campaign === null || ! $campaign->isOpen()) {
            return;
        }

        Notification::route('mail', $this->address)
            ->notify(new CampaignInvitationNotification($campaign, $this->names, $this->isReminder));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
