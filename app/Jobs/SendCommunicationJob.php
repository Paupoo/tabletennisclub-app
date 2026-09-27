<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Communications\Models\CommunicationRecipient;
use App\Jobs\Concerns\RetriesWhileRateLimited;
use App\Mail\CommunicationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * One address of a communication.
 *
 * Shares the `invitations` limiter rather than taking one of its own: bulk,
 * member-facing, nobody waiting on it — and Gmail counts the burst, not the
 * job type. Carries the recipient's id, so a row pruned or already sent is
 * simply skipped.
 */
class SendCommunicationJob implements ShouldQueue
{
    use Queueable, RetriesWhileRateLimited;

    public function __construct(public int $recipientId) {}

    /**
     * Shown on the communication's page, where the author can retry it.
     */
    public function failed(?Throwable $exception): void
    {
        CommunicationRecipient::query()->whereKey($this->recipientId)->update([
            'status' => CommunicationRecipient::STATUS_FAILED,
            'error' => $exception?->getMessage() ?? __('Unknown error'),
        ]);
    }

    public function handle(): void
    {
        $recipient = CommunicationRecipient::with('communication.author')->find($this->recipientId);

        if ($recipient === null || $recipient->status === CommunicationRecipient::STATUS_SENT) {
            return;
        }

        Mail::to($recipient->email)->sendNow(new CommunicationMail($recipient->communication, $recipient));

        $recipient->update([
            'status' => CommunicationRecipient::STATUS_SENT,
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimited('invitations')];
    }
}
