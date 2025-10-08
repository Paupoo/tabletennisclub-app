<?php

namespace App\Actions\Payments;

use App\Mail\PaymentInvitationEmail;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Sum;

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
                ->load( 'payable.user', 'payable.season'))
            );

        return back()
            ->withInput([
                'success' => __('The payment invitation has been sent'),
            ]);
    }
}