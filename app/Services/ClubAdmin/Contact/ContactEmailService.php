<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Contact;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Mail\CustomEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactEmailService
{
    /**
     * Contact statuses that an email template is allowed to apply on send.
     *
     * @var list<string>
     */
    private const ALLOWED_STATUSES = ['new', 'processed', 'rejected'];

    public function __construct(
        private readonly EmailTemplateRenderer $renderer = new EmailTemplateRenderer,
    ) {}

    /**
     * Will send Custom Email with or without copy for the club
     *
     * @param  array{subject: string, body: string}  $mailData
     */
    public function sendCustom(Contact $contact, array $mailData, User $user, bool $sendCopy = false): void
    {
        $senderName = trim($user->first_name . ' ' . $user->last_name) ?: $this->clubName();

        $payload = $this->buildMailData($contact, $mailData['subject'], $mailData['body'], $senderName);

        if ($sendCopy) {
            Mail::to($user->email)->send(new CustomEmail($payload, true));
        }

        Mail::to($contact->email)->send(new CustomEmail($payload));

        Log::info(__('Personalized email was sent successfully'), [
            'contact_id' => $contact->id,
            'subject' => $mailData['subject'],
            'admin_user' => $user->id,
        ]);
    }

    /**
     * Render a database template, e-mail it to the contact through the generic
     * {@see CustomEmail} mailable, then apply the template's status if valid.
     *
     * @throws \InvalidArgumentException when the template key is unknown/inactive
     */
    public function sendTemplate(Contact $contact, string $template): string
    {
        $rendered = $this->renderer->render($template, $contact);

        $clubName = $this->clubName();
        $payload = $this->buildMailData($contact, $rendered['subject'], $rendered['body'], $clubName);

        Mail::to($contact->email)->send(new CustomEmail($payload));

        $this->applyStatus($contact, $rendered['apply_status']);

        Log::info('Template email sent', [
            'contact_id' => $contact->id,
            'template' => $template,
        ]);

        return __('Email :template successfully sent', ['template' => $template]);
    }

    /**
     * Apply the template's status to the contact when it is one of the allowed
     * contact statuses; otherwise log a warning and leave the contact untouched.
     */
    private function applyStatus(Contact $contact, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            Log::warning('Ignored invalid apply_status on email template', [
                'contact_id' => $contact->id,
                'apply_status' => $status,
            ]);

            return;
        }

        $contact->update(['status' => $status]);
    }

    /**
     * Shape an e-mail into the payload expected by {@see CustomEmail}.
     *
     * @return array{contact: Contact, message: string, sender_name: string, club_name: string, subject: string}
     */
    private function buildMailData(Contact $contact, string $subject, string $message, string $senderName): array
    {
        return [
            'contact' => $contact,
            'message' => $message,
            'sender_name' => $senderName,
            'club_name' => $this->clubName(),
            'subject' => $subject,
        ];
    }

    /**
     * Resolve the club's display name from the database, falling back to config.
     */
    private function clubName(): string
    {
        return Club::own()?->name ?? (string) config('app.name');
    }
}
