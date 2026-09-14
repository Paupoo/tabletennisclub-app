<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Notifications\InterclubSelectionNotification;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification;

/**
 * One selected player's convocation, queued.
 *
 * A lineup is a dozen recipients, and each of these builds an ICS attachment
 * before it leaves. Sent inline they were a dozen blocking SMTP round trips in
 * the middle of a Livewire request: the screen froze with nothing to say for
 * itself, and captains clicked "send" again.
 *
 * Takes the `convocations` limiter declared in {@see AppServiceProvider}, like
 * every other mailing that carries a date the member has to answer for.
 *
 * Carries ids rather than models: a member archived between the fan-out and the
 * send is skipped, where a serialised model would fail the job.
 */
class SendInterclubSelectionJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(
        public int $interclubId,
        public int $userId,
        public string $captainMessage = '',
    ) {}

    public function handle(): void
    {
        $interclub = Interclub::find($this->interclubId);
        $recipient = User::find($this->userId);

        if ($interclub === null || $recipient === null) {
            return;
        }

        Notification::send($recipient, new InterclubSelectionNotification($interclub, $this->captainMessage));
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
