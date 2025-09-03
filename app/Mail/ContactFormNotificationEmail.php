<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\ContactReasonEnum;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormNotificationEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /**
     * Create a new message instance.
     */
    public function __construct(private Contact $contact)
    {
        //
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.contact-form-mail-notification',
            with: [
                'first_name' => $this->contact->first_name ?? '',
                'last_name' => $this->contact->last_name ?? '',
                'email' => $this->contact->email ?? '',
                'phone' => $this->contact->phone ?? '',
                'interest' => $this->contact->interest,
                'message' => $this->contact->message ?? '',
                'membership_family_members' => $this->contact->membership_family_members ?? '',
                'membership_competitors' => $this->contact->membership_competitors ?? '',
                'membership_training_sessions' => $this->contact->membership_training_sessions ?? '',
                'membership_total_cost' => $this->contact->membership_total_cost ?? '',
            ]
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Formulaire de contact - ' . ($this->contact->interest->getLabel() ?? 'Demande générale'),
        );
    }
}
