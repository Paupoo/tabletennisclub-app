<?php

namespace App\Mail;

use App\Actions\Payments\GeneratePaymentQR;
use App\Models\Payment;
use App\Models\Season;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $qrCode;
    public string $beneficiary = 'CTT Ottignies-Blocry ASBL';
    public string $IBAN = 'BE23732333208791';
    public string $BIC = 'CREGBEBB';

    /**
     * To do
     * @param \App\Models\Payment $payment
     */
    public function __construct(public Payment $payment)
    {
        $this->qrCode = new GeneratePaymentQR()($payment);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('test@example.com', 'CTT Ottignies-Blocry'),
            subject: 'Payment Invitation for the seasons',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-invitation',
            with: [
                'instructions' => __('Veuillez effectuer le versement avant le ' . today()->addDays(30)->format('d/m/Y')),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
