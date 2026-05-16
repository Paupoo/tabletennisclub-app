<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TournamentResultsMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public User $recipient,
        public string $emailSubject,
        public string $emailBody,
        public Collection $rankings,
    ) {}

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tournament-results',
        );
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(config('mail.from.address'), config('app.name')),
            subject: $this->emailSubject,
        );
    }
}
