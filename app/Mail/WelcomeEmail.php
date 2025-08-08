<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue dans notre club !',
            from: config('mail.from.address') ?? 'cttottigniesblocry@gmail.com',
            replyTo: config('mail.from.address') ?? 'cttottigniesblocry@gmail.com',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.welcome-email',
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