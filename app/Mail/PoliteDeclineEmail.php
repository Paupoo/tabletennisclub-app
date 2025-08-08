<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PoliteDeclineEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nous ne pouvons donner suite à votre demande',
            from: config('mail.from.address') ?? 'cttottigniesblocry@gmail.com',
            replyTo: config('mail.from.address') ?? 'cttottigniesblocry@gmail.com',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.polite-decline',
            with: [
                'contact' => $this->contact,
                'clubName' => config('app.name'),
            ]
        );
    }

    public function attachments(): array
    {
        return [
            // Optionnel : joindre une brochure du club
            // Attachment::fromPath('/path/to/brochure.pdf')
            //     ->as('Brochure_Club.pdf')
            //     ->withMime('application/pdf'),
        ];
    }
}