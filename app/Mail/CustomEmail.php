<?php

declare(strict_types=1);

namespace App\Mail;

use App\Support\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $emailData,
        public bool $isCopy = false
    ) {}

    public function attachments(): array
    {
        return [
            // Possibilité d'ajouter des pièces jointes dynamiques
            // basées sur le contenu du message ou le type de contact
        ];
    }

    public function content(): Content
    {
        return new Content(
            markdown: $this->isCopy ? 'mail.custom-copy-email' : 'mail.custom-email',
            with: [
                'contact' => $this->emailData['contact'],
                'customMessage' => $this->processMessage($this->emailData['message']),
                'senderName' => $this->emailData['sender_name'],
                'clubName' => $this->emailData['club_name'],
                'isCopy' => $this->isCopy,
                'subject' => $this->emailData['subject'],
            ]
        );
    }

    public function envelope(): Envelope
    {
        $subject = $this->emailData['subject'];

        // Ajouter [COPIE] si c'est une copie pour l'admin
        if ($this->isCopy) {
            $subject = '[COPIE] ' . $subject;
        }

        return new Envelope(
            subject: $subject,
            from: new Address(
                address: config('mail.from.address'),
                name: config('app.name') ?? config('mail.from.name')
            ),
            replyTo: config('mail.from.address'),
        );
    }

    /**
     * Render the markdown body and resolve the legacy `{{ $contact->… }}` variables.
     *
     * The body comes from <x-markdown-editor>: Markdown::safe() escapes raw HTML
     * and drops unsafe links, and turns bare URLs into links. The variables
     * carry data a visitor typed in the contact form, so they are swapped for
     * inert tokens first and replaced by their escaped values after rendering:
     * substituted before, a name like `[click](https://…)` would become a link.
     */
    private function processMessage(string $message): string
    {
        $contact = $this->emailData['contact'];

        $replacements = [
            '{{ $contact->first_name }}' => $contact->first_name,
            '{{ $contact->last_name }}' => $contact->last_name,
            '{{ $contact->email }}' => $contact->email,
            '{{ $contact->phone }}' => $contact->phone ?? 'Non renseigné',
            '{{ $contact->interest }}' => $contact->interest?->getLabel() ?? 'Non spécifié',
            '{{ config(\'app.name\') }}' => config('app.name'),
            '{{ date(\'Y\') }}' => date('Y'),
            '{{ date(\'d/m/Y\') }}' => date('d/m/Y'),
        ];

        $tokens = [];

        foreach ($replacements as $placeholder => $value) {
            $token = 'CUSTOMEMAILVARIABLE' . count($tokens) . 'END';
            $tokens[$token] = e((string) $value);
            $message = str_replace($placeholder, $token, $message);
        }

        return strtr(Markdown::safe($message), $tokens);
    }
}
