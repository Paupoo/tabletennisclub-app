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
     * Store a new contact and send notification emails.
     *
     * @param  array  $validated  Validated contact data
     * @return Contact The created contact
     *
     * @throws Exception
     */
    public function execute(array $validated): Contact
    {
        // Create the contact
        $contact = Contact::create($validated);

        try {
            Mail::to($contact->email)->send(new ContactFormConfirmationEmail($contact));

            $clubEmail = Club::own()?->email_contact
                ?? throw new RuntimeException('Club has no contact email configured.');

            Mail::to($clubEmail)->send(new ContactFormNotificationEmail($contact));

            Log::info('Contact created and emails sent', [
                'contact_id' => $contact->id,
                'email' => $contact->email,
            ]);

        } catch (Exception $e) {
            Log::error('Error sending contact notification emails', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            // Still return the contact - it was created successfully
            // Email failure shouldn't prevent the contact from being stored
            throw $e;
        }

        return $contact;
    }
}
