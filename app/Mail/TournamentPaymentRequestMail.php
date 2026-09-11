<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TournamentPaymentRequestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $beneficiary = 'CTT Ottignies-Blocry ASBL';

    public string $BIC;

    public string $IBAN;

    public function __construct(
        public Tournament $tournament,
        public Payment $payment,
        public Carbon $deadline,
    ) {
        $this->BIC = Club::ourClub()->first()->bic;
        $this->IBAN = Club::ourClub()->first()->bank_account_formatted;
    }

    /**
     * The payment QR travels as a named attachment, which the view references as
     * `cid:qr-paiement.png`.
     *
     * Two reasons, both learned the hard way. Gmail drops a `data:` source from
     * an `<img>`, so the message showed its alt text and nothing else in
     * production while Mailpit rendered it fine in development. And this mailable
     * is queued: raw PNG bytes held in a property would be put through
     * `json_encode` with the job payload, where they fail as malformed UTF-8 —
     * building them here keeps them off the queue entirely.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => (new GeneratePaymentQR)->png($this->payment), 'qr-paiement.png')
                ->withMime('image/png'),
        ];
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
