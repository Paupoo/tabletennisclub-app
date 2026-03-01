<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Contact;

use App\Mail\ContactFormConfirmationEmail;
use App\Mail\ContactFormNotificationEmail;
use App\Models\ClubAdmin\Contact\Contact;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            // Send confirmation email to contact
            Mail::to($contact->email)->send(new ContactFormConfirmationEmail($contact));

            // Send notification email to club admin
            Mail::to(config('app.club_email'))
                ->send(new ContactFormNotificationEmail($contact));

            Log::info(__('Contact created and emails sent'), [
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
