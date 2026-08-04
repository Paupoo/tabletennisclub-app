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
        $payment->increment('invitation_counter');
        $payment->forceFill(['last_reminded_at' => now()])->save();
    }
}
