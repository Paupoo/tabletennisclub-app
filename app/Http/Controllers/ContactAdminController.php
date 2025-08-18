<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendEmailRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Mail\CustomEmail;
use App\Mail\MembershipInfoDetailEmail;
use App\Mail\PoliteDeclineEmail;
use App\Mail\RequestInfoEmail;
use App\Mail\WelcomeEmail;
use App\Models\Contact;
use App\Support\Breadcrumb;
use App\Support\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactAdminController extends Controller
{
    public function index()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->toArray();

        $contacts = Contact::latest()->paginate(5);

        $stats = collect([
            'totalNew' => Contact::where('status', 'new')->count(),
            'totalPending' => Contact::where('status', 'pending')->count(),
            'totalProcessed' => Contact::where('status', 'processed')->count(),
            'totalRejected' => Contact::where('status', 'rejected')->count(),
        ]);
        
        return view('admin.contacts.index', compact('contacts','breadcrumbs', 'stats'));
    }

    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);


        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->current('Détails du contact')
            ->toArray();

        return view('admin.contacts.show', compact('contact', 'breadcrumbs'));
    }

    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $this->authorize('update', $contact);
        $validated = $request->validated();
        $contact->update($validated);

        return redirect()->back()->with('success', 'Statut mis à jour.');
    }

    public function destroy(Contact $contact)
    {
        $this->authorize('destroy', $contact);

        $contact->delete();

        return redirect()->route('admin.contacts.index')->with('success', 'Contact supprimé.');
    }
    public function sendEmail(SendEmailRequest $request, Contact $contact)
    {
        $this->authorize('sendEmail', $contact);

        $template = $request->validated('template');
        try {
            switch ($template) {
                case 'welcome':
                    Mail::to($contact->email)->send(new WelcomeEmail($contact));
                    $message = 'Email de bienvenue envoyé avec succès !';
                    break;

                case 'membership_info':
                    Mail::to($contact->email)->send(new MembershipInfoDetailEmail($contact));
                    $message = 'Informations d\'adhésion envoyées avec succès !';
                    break;

                case 'polite_decline':
                    Mail::to($contact->email)->send(new PoliteDeclineEmail($contact));
                    $message = 'Email de refus poli envoyé avec succès !';
                    // Optionnellement, mettre à jour le statut
                    $contact->update(['status' => 'rejected']);
                    break;

                case 'request_info':
                    Mail::to($contact->email)->send(new RequestInfoEmail($contact));
                    $message = 'Demande d\'informations envoyée avec succès !';
                    break;

                case 'custom':
                    // Rediriger vers un formulaire de composition d'email personnalisé
                    return redirect()->route('admin.contacts.compose-email', $contact);
                    break;

                default:
                    throw new \Exception('Template non géré');
            }

            // Logger l'action
            Log::info('Email envoyé', [
                'contact_id' => $contact->id,
                'template' => $template,
                'admin_user' => auth()->user()->id
            ]);

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Erreur envoi email', [
                'contact_id' => $contact->id,
                'template' => $template,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }
    }

    public function composeEmail(Contact $contact)
    {
        $this->authorize('sendEmail', $contact);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->current('Email personnalisé')
            ->toArray();

        return view('admin.contacts.compose-email', compact('contact', 'breadcrumbs'));
    }

    public function sendCustomEmail(Request $request, Contact $contact)
    {
        $this->authorize('sendEmail', $contact);
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'send_copy' => 'boolean'
        ]);

        try {
            $emailData = [
                'subject' => $request->subject,
                'message' => $request->message,
                'contact' => $contact,
                'sender_name' => auth()->user()->fullName,
                'club_name' => config('app.name')
            ];

            // Envoi de l'email principal
            Mail::to($contact->email)->send(new CustomEmail($emailData));

            // Optionnel : envoyer une copie à l'administrateur
            if ($request->boolean('send_copy')) {
                Mail::to(auth()->user()->email)->send(new CustomEmail($emailData, true));
            }

            // Logger l'action
            Log::info('Email personnalisé envoyé', [
                'contact_id' => $contact->id,
                'subject' => $request->subject,
                'admin_user' => auth()->user()->id
            ]);

            return redirect()
                ->route('admin.contacts.show', $contact)
                ->with('success', 'Email personnalisé envoyé avec succès !');

        } catch (\Exception $e) {
            Log::error('Erreur envoi email personnalisé', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage()
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
        }
    }
}
