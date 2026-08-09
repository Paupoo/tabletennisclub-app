<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Contact;

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class StoreContactAction
{
    /**
     * Store a new contact and queue its notification emails.
     *
     * The contact is what matters: it is the only thing an anonymous visitor
     * can leave us, and it is already saved by the time the mails are handed
     * over. Both go to the queue so a slow or unreachable relay cannot hold up
     * the response, and a failure here is logged and swallowed rather than
     * rethrown — the visitor would otherwise be told to send again a message
     * we already have.
     *
     * @param  array  $validated  Validated contact data
     * @return Contact The created contact
     */
    public function execute(array $validated): Contact
    {
        // Create the contact
        $contact = Contact::create($validated);

        try {
            Mail::to($contact->email)->queue(new ContactFormConfirmationEmail($contact));

            $clubEmail = Club::own()?->email_contact
                ?? throw new RuntimeException('Club has no contact email configured.');

            Mail::to($clubEmail)->queue(new ContactFormNotificationEmail($contact));

            Log::info('Contact created and emails queued', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);

        } catch (Exception $e) {
            Log::error('Error queueing contact notification emails', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $contact;
    }
}
