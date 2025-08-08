<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class CustomEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $emailData,
        public bool $isCopy = false
    ) {}

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
                name: $this->emailData['sender_name'] ?? config('mail.from.name')
            ),
            replyTo: config('mail.from.address'),
        );
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

    public function attachments(): array
    {
        return [
            // Possibilité d'ajouter des pièces jointes dynamiques
            // basées sur le contenu du message ou le type de contact
        ];
    }

    /**
     * Traite le message pour remplacer les variables et formater le texte
     */
    private function processMessage(string $message): string
    {
        $contact = $this->emailData['contact'];
        
        // Remplacement des variables courantes
        $replacements = [
            '{{ $contact->first_name }}' => $contact->first_name,
            '{{ $contact->last_name }}' => $contact->last_name,
            '{{ $contact->email }}' => $contact->email,
            '{{ $contact->phone }}' => $contact->phone ?? 'Non renseigné',
            '{{ $contact->interest }}' => $contact->interest ?? 'Non spécifié',
            '{{ config(\'app.name\') }}' => config('app.name'),
            '{{ date(\'Y\') }}' => date('Y'),
            '{{ date(\'d/m/Y\') }}' => date('d/m/Y'),
        ];

        // Remplacements plus avancés
        $message = str_replace(array_keys($replacements), array_values($replacements), $message);

        // Formatage basique : convertir les sauts de ligne en <br>
        $message = nl2br($message);

        // Détection et formatage des URLs
        $message = $this->linkifyUrls($message);

        return $message;
    }

    /**
     * Convertit les URLs en liens cliquables
     */
    private function linkifyUrls(string $text): string
    {
        $pattern = '/((http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?)/';
        return preg_replace($pattern, '<a href="$1" target="_blank" style="color: #2980b9;">$1</a>', $text);
    }
}