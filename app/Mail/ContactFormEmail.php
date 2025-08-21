<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\ContactReasonEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    /**
     * Create a new message instance.
     */
    public function __construct(private array $formData)
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
        $interestEnum = isset($this->formData['interest'])
            ? ContactReasonEnum::tryFrom($this->formData['interest'])
            : null;

        return new Content(
            markdown: 'mail.contact-form-mail',
            with: [
                'first_name' => $this->formData['first_name'] ?? '',
                'last_name' => $this->formData['last_name'] ?? '',
                'email' => $this->formData['email'] ?? '',
                'phone' => $this->formData['phone'] ?? '',
                'interest' => $interestEnum?->getLabel() ?? 'Demande générale',
                'message' => $this->formData['message'] ?? '',
                'membership_family_members' => $this->formData['membership_family_members'] ?? '',
                'membership_competitors' => $this->formData['membership_competitors'] ?? '',
                'membership_training_sessions' => $this->formData['membership_training_sessions'] ?? '',
                'membership_total_cost' => $this->formData['membership_total_cost'] ?? '',
                'tableData' => [
                    [
                        'Type' => 'Licence(s) récréatives',
                        'Quantité' => $this->formData['membership_family_members'] ?? 0,
                    ],
                    [
                        'Type' => 'Licence(s) sportive(s)',
                        'Quantité' => $this->formData['membership_competitors'] ?? 0,
                    ],
                    [
                        'Type' => 'Entrainement(s) dirigé(s)',
                        'Quantité' => $this->formData['membership_training_sessions'] ?? 0,
                    ],
                ],
            ]
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $interestEnum = isset($this->formData['interest'])
            ? ContactReasonEnum::tryFrom($this->formData['interest'])
            : null;

        return new Envelope(
            subject: 'Formulaire de contact - ' . ($interestEnum->getLabel() ?? 'Demande générale'),
        );
    }
}
