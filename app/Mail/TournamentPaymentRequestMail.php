<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TournamentPaymentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $beneficiary = 'CTT Ottignies-Blocry ASBL';

    public string $BIC;

    public string $IBAN;

    public string $qrCode;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
        public Carbon $deadline,
    ) {
        $this->qrCode = (new GeneratePaymentQR)($payment);
        $this->BIC = Club::ourClub()->first()->bic;
        $this->IBAN = Club::ourClub()->first()->bank_account;
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.tournament-payment-request');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@cttottigniesblocry.be', 'CTT Ottignies-Blocry'),
            subject: __('Inscription') . ' — ' . $this->tournament->name,
        );
    }
}
