<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPaymentReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::with('payable')->find($this->paymentId);

        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        $recipients = $this->recipients($payment->payable);

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(
                new PaymentInvitationEmail($payment, __('Please settle your payment as soon as possible.'))
            );
        }
        // One write, not two: the counter and the date describe the same event, and
        // an increment followed by a separate save can leave the count raised with
        // no date behind it.
        $payment->forceFill([
            'invitation_counter' => $payment->invitation_counter + 1,
            'last_reminded_at' => now(),
        ])->save();
    }

    /**
     * Who to write to.
     *
     * A minor's payment reminder has to reach whoever actually pays it — every
     * guardian, one message each. A non-member has no account: the address
     * encoded with their registration. A payable naming nobody (a bar order)
     * has nobody to chase; loading a `user` it does not have would throw.
     *
     * @return list<string>
     */
    private function recipients(?Model $payable): array
    {
        if ($payable instanceof ExternalRegistration) {
            return $payable->isAnonymized() || $payable->email === null ? [] : [$payable->email];
        }

        if ($payable === null || ! $payable->isRelation('user')) {
            return [];
        }

        $user = $payable->loadMissing('user')->getRelation('user');

        return $user instanceof User ? $user->contactEmails() : [];
    }
}
