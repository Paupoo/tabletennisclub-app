<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentInvitationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $beneficiary = 'CTT Ottignies-Blocry ASBL';

    public string $BIC;

    public string $IBAN;

    public string $qrCode;

    public function __construct(
        public Payment $payment,
        public ?string $instructions = null,
    ) {
        $this->BIC = Club::ourClub()->first()->bic;
        $this->IBAN = Club::ourClub()->first()->bank_account_formatted;
        $this->qrCode = (new GeneratePaymentQR)($payment);
        $this->instructions ??= __('Please make the payment before ' . today()->addDays(30)->format('d/m/Y'));
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.payment-invitation');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('test@example.com', 'CTT Ottignies-Blocry'),
            subject: __('Payment Invitation for the seasons'),
        );
    }
}
