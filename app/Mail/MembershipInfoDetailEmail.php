<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MembershipInfoDetailEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact
    ) {}

    public function attachments(): array
    {
        return [
            // Optionnel : joindre une brochure du club
            // Attachment::fromPath('/path/to/brochure.pdf')
            //     ->as('Brochure_Club.pdf')
            //     ->withMime('application/pdf'),
        ];
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

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenue dans notre club !',
            from: new Address(
                address: config('mail.from.address'),
                name: config('app.name') ?? config('mail.from.name')
            ),
            replyTo: config('mail.from.address'),
        );
    }
}
