<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\RetriesWhileRateLimited;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Notification as Notifier;

/**
 * One training notice, to one person, out of the request.
 *
 * Cancelling a session used to mail every enrolled member from inside the
 * click: the committee watched the button spin for half a minute while the
 * SMTP server answered twenty times. The screens now hand each recipient to
 * this job and return at once.
 *
 * Under the `convocations` limiter: a session called off tonight has to reach
 * the families in a couple of minutes, not three quarters of an hour behind an
 * invitation mailing nobody is waiting on.
 *
 * A member deleted between the click and the send is simply skipped.
 */
class SendTrainingNoticeJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public object $notifiable,
        public Notification $notification,
    ) {}

    /**
     * Hand each recipient its own job.
     *
     * @param  iterable<object>  $notifiables
     */
    public static function toEach(iterable $notifiables, Notification $notification): void
    {
        foreach ($notifiables as $notifiable) {
            self::dispatch($notifiable, clone $notification);
        }
    }

    public function handle(): void
    {
        // sendNow() so the mail leaves inside the throttled job: notify() would
        // hand a queued notification to a second, unthrottled job.
        Notifier::sendNow($this->notifiable, $this->notification);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('convocations')];
    }
}
