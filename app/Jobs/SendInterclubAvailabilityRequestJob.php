<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\InterclubAvailabilityRequestNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * Asks one member for their availability. Queued like {@see SendInterclubSelectionJob}.
 */
class SendInterclubAvailabilityRequestJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(public int $interclubId, public int $userId) {}

    public function handle(): void
    {
        $interclub = Interclub::find($this->interclubId);
        $recipient = User::find($this->userId);

        if ($interclub === null || $recipient === null) {
            return;
        }

        Notification::send($recipient, new InterclubAvailabilityRequestNotification($interclub));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
