<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Ops alert sent to the admins when the queue worker looks down.
 *
 * Deliberately NOT queued (no ShouldQueue) and sent with Mail::sendNow():
 * the broken queue is precisely what this mail reports.
 */
class QueueStalledMail extends Mailable
{
    public function __construct(
        public int $pendingCount,
        public int $oldestMinutes,
        public int $failedCount,
    ) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.queue-stalled',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('[:app] Queue alert — the worker looks down', ['app' => config('app.name')]),
        );
    }
}
