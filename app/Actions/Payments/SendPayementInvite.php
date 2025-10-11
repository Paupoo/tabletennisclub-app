<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Mail\PaymentInvitationEmail;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class SendPayementInvite
{
    public function __invoke(Payment $payment): RedirectResponse
    {
        // Generate the QRCode
        $QRGenerator = new GeneratePaymentQR()($payment);
        $payment = $payment->load('payable.user');

        // Send an email with payment instructions
        Mail::to($payment->payable->user)
            ->send(new PaymentInvitationEmail($payment
                ->load('payable.user', 'payable.season'))
            );

        return back()
            ->withInput([
                'success' => __('The payment invitation has been sent'),
            ]);
    }
}
