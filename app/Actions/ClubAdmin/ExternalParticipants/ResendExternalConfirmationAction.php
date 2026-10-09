<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\ExternalParticipants;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Mail\ExternalCampEnrolmentEmail;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a non-member's confirmation again, with what is still to pay.
 *
 * After a corrected address, or for someone who first paid cash and now owes
 * the rest. Nothing is sent once nothing is owed: the mail asks for money.
 */
final readonly class ResendExternalConfirmationAction
{
    /**
     * @return bool whether a mail left
     */
    public function __invoke(ExternalRegistration $registration): bool
    {
        if ($registration->isAnonymized() || $registration->status !== 'enrolled' || $registration->email === null) {
            return false;
        }

        $claim = $registration->payments()
            ->where('status', 'pending')
            ->where(fn ($method) => $method->where('payment_method', '!=', 'refund')->orWhereNull('payment_method'))
            ->latest('id')
            ->first();

        if (! $claim instanceof Payment || $claim->balance() <= 0.0) {
            return false;
        }

        Mail::to($registration->email)->send(new ExternalCampEnrolmentEmail($claim));
        $claim->increment('invitation_counter');

        return true;
    }
}
