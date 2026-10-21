<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
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

/**
 * The one message a registration sends: the tournament, its calendar entry and,
 * when there is something to pay, how to pay it.
 *
 * It used to be two messages, the first saying "confirmed" and the second "not
 * final until paid". The payment block is now the only difference between a
 * free and a paid registration.
 */
class TournamentRegistrationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $beneficiary = 'CTT Ottignies-Blocry ASBL';

    public ?string $BIC = null;

    public ?string $IBAN = null;

    public function __construct(
        public Tournament $tournament,
        public User $player,
        public ?Payment $payment = null,
        public ?Carbon $deadline = null,
    ) {
        // Only a payment needs the bank: a free tournament is announced even
        // where no club row exists yet.
        if ($payment !== null) {
            $club = Club::ourClub()->firstOrFail();
            $this->BIC = $club->bic;
            $this->IBAN = $club->bank_account_formatted;
        }
    }

    /**
     * The calendar entry and the payment QR travel as named attachments; the
     * view references the QR as `cid:qr-paiement.png`.
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
        $attachments = [];

        if ($this->tournament->start_date !== null) {
            $attachments[] = Attachment::fromData(fn (): string => $this->ics(), 'tournament.ics')
                ->withMime('text/calendar');
        }

        if ($this->payment !== null) {
            $attachments[] = Attachment::fromData(fn (): string => (new GeneratePaymentQR)->png($this->payment), 'qr-paiement.png')
                ->withMime('image/png');
        }

        return $attachments;
    }

    public function content(): Content
    {
        $this->tournament->loadMissing('rooms');

        return new Content(markdown: 'mail.tournament-registration', with: [
            // A tournament entered on its own day is paid at the desk: no
            // payment is opened, but the player still learns the amount.
            'onSitePrice' => $this->payment === null && $this->tournament->isPaid()
                ? $this->tournament->priceFor($this->player)
                : null,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@cttottigniesblocry.be', 'CTT Ottignies-Blocry'),
            subject: __(':tournament — Registration recorded', ['tournament' => $this->tournament->name]),
        );
    }

    private function ics(): string
    {
        $tournament = $this->tournament->loadMissing('rooms');
        $location = $tournament->rooms->pluck('name')->join(', ') ?: '—';

        $start = $tournament->start_date->format('Ymd');
        $startTime = str_replace(':', '', $tournament->start_time ?? '000000');
        $dtStart = $start . ($tournament->start_time ? 'T' . $startTime . '00' : '');
        $dtEnd = $tournament->end_date
            ? $tournament->end_date->format('Ymd\THis')
            : $tournament->start_date->format('Ymd\THis');
        $uid = 'tournament-' . $tournament->id . '@tabletennisclub';
        $name = str_replace(["\r", "\n", ',', ';'], [' ', ' ', '\,', '\;'], $tournament->name);
        $loc = str_replace(["\r", "\n", ',', ';'], [' ', ' ', '\,', '\;'], $location);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TableTennisClub//Tournament//EN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'SUMMARY:' . $name,
            'DTSTART:' . $dtStart,
            'DTEND:' . $dtEnd,
            'LOCATION:' . $loc,
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";
    }
}
