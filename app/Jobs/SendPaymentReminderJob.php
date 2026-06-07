<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
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

        if ($payment->payable instanceof Subscription) {
            $payment->load('payable.season');
        }

        if (! $payment->payable?->user) {
            return;
        }

        Mail::to($payment->payable->user)->send(new PaymentInvitationEmail($payment));
        $payment->increment('invitation_counter');
    }
}
