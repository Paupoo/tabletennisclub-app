<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Payments;

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Mail\PaymentInvitationEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class SendPayementInvite
{
    public function __invoke(Payment $payment): RedirectResponse
    {
        $payment = $payment->load('payable.user');

        // Send an email with payment instructions. A minor's invitation has to
        // reach whoever actually pays it, hence the contact address and not the
        // login one.
        $recipient = $payment->payable->user->contactEmail();

        if ($recipient !== null) {
            Mail::to($recipient)
                ->send(
                    new PaymentInvitationEmail($payment
                        ->load('payable.user', 'payable.season'))
                );
        }

        // Increment invitation counter
        $payment->invitation_counter++;
        $payment->save();

        return back()
            ->with([
                'success' => __('The payment invitation has been sent'),
            ]);
    }
}
