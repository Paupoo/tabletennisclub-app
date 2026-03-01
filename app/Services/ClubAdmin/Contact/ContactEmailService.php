<?php

declare(strict_types=1);

namespace App\Services\ClubAdmin\Contact;

use App\Mail\CustomEmail;
use App\Mail\MembershipInfoDetailEmail;
use App\Mail\PoliteDeclineEmail;
use App\Mail\RequestInfoEmail;
use App\Mail\WelcomeEmail;
use App\Models\ClubAdmin\Contact\Contact;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class ContactEmailService
{
    /**
     * Will send Custom Email with or without copy for the club
     */
    public function sendCustom(Contact $contact, array $mailData, User $user, bool $sendCopy = false): void
    {
        if ($sendCopy) {
            Mail::to($user->email)->send(new CustomEmail($mailData, true));
        }

        Mail::to($contact->email)->send(new CustomEmail($mailData));

        Log::info(__('Personalized email was sent successfully'), [
            'contact_id' => $contact->id,
            'subject' => $mailData['subject'],
            'admin_user' => $user->id,
        ]);
    }

    /**
     * Will send the correct email matching the template
     */
    public function sendTemplate(Contact $contact, string $template): string
    {
        return match ($template) {
            'welcome' => $this->send($contact, new WelcomeEmail($contact), 'welcome'),
            'membership_info' => $this->send($contact, new MembershipInfoDetailEmail($contact), 'membership_info'),
            'request_info' => $this->send($contact, new RequestInfoEmail($contact), 'request_info'),
            'polite_decline' => $this->sendAndReject($contact, new PoliteDeclineEmail($contact)),
            default => throw new InvalidArgumentException(__("'Template not managed' : {$template}"))
        };
    }

    /**
     * @param Contact $contact
     * @param Mailable $mail
     * @param string $template
     * @return string
     */
    private function send(Contact $contact, Mailable $mail, string $template): string
    {
        Mail::to($contact->email)->send($mail);
        Log::info('Email envoyé', ['contact_id' => $contact->id, 'template' => $template]);

        return __("Email {$template} successfully sent");
    }

    /**
     * Send an e-mail with template polite reject and update contact status
     */
    private function sendAndReject(Contact $contact, Mailable $mail): string
    {
        $contact->update(['status' => 'rejected']);

        return $this->send($contact, $mail, 'polite_decline');
    }
}
