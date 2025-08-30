<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactFormEmail;
use App\Mail\WelcomeEmail;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request)
    {

        $validated = $request->validated();

        try {
            $contact = Contact::create($validated);

            // Envoyer un email
            // Mail::to(config('app.club_email'))->send(new ContactFormMail($validated)); --> uncomment this line once the mail of the club is correctly configured
            Mail::to($request->email)->send(new WelcomeEmail($contact));
            Mail::to('aurelien.paulus@gmail.com')->send(new ContactFormEmail($contact));


            // Log pour le développement
            Log::info('Nouveau message de contact', $validated);

            return redirect('/#contact')
                ->with('success', 'Votre message a été envoyé avec succès ! Nous vous répondrons dans les plus brefs délais.');

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'envoi du message de contact', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return redirect('/#contact')
                ->with('error', 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer.')
                ->withInput();
        }
    }
}
