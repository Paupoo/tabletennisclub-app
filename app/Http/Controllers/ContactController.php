<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactFormEmail;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request)
    {

        $validated = $request->validated();

        try {
            // Ici vous pouvez sauvegarder en base de données
            // Contact::create($validated);

            Contact::create($validated);
            // Mail::to(config('app.club_email'))->send(new ContactFormMail($validated));
            Mail::to('aurelien.paulus@gmail.com')->send(new ContactFormEmail($validated));
            // Ou envoyer un email

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
