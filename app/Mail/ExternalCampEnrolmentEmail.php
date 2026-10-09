<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirms a non-member's place on a stage and asks them to pay, in one mail.
 *
 * Without an account, this is the only trace the person gets of their
 * registration: it says who is enrolled, in what and when, then carries the
 * same bank details and QR as any invitation to pay.
 */
class ExternalCampEnrolmentEmail extends PaymentInvitationEmail
{
    public function content(): Content
    {
        return new Content(markdown: 'mail.external-camp-enrolment');
    }

    public function envelope(): Envelope
    {
        $registration = $this->payment->payable;
        $camp = $registration instanceof ExternalRegistration ? $registration->registrable->name : '';

        return parent::envelope()->subject(__('Enrolment confirmed — :camp', ['camp' => $camp]));
    }
}
