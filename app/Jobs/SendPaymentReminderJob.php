<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::with(['payable.user'])->find($this->paymentId);

        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        if (! $payment->payable?->user) {
            return;
        }

        // A minor's payment reminder has to reach whoever actually pays it.
        $recipient = $payment->payable->user->contactEmail();

        if ($recipient === null) {
            return;
        }

        Mail::to($recipient)->send(
            new PaymentInvitationEmail($payment, __('Please settle your payment as soon as possible.'))
        );
        // One write, not two: the counter and the date describe the same event, and
        // an increment followed by a separate save can leave the count raised with
        // no date behind it.
        $payment->forceFill([
            'invitation_counter' => $payment->invitation_counter + 1,
            'last_reminded_at' => now(),
        ])->save();
    }
}
