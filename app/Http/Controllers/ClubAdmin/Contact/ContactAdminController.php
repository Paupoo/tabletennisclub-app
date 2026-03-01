<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClubAdmin\Contact\SendCustomEmailRequest;
use App\Http\Requests\ClubAdmin\Contact\SendEmailRequest;
use App\Http\Requests\ClubAdmin\Contact\UpdateContactRequest;
use App\Models\ClubAdmin\Contact\Contact;
use App\Services\ClubAdmin\Contact\ContactEmailService;
use App\Support\Breadcrumb;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ContactAdminController extends Controller
{
    public function __construct(private readonly ContactEmailService $emailService) {}

    public function composeEmail(Contact $contact): View
    {
        $this->authorize('sendEmail', $contact);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->current(__('Personalized email'))
            ->toArray();

        return view('clubAdmin.contacts.contact.compose-email', compact('contact', 'breadcrumbs'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->route('clubAdmin.contacts.index')->with('success', __('Contact deleted successfully'));
    }

    public function index(): View
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->toArray();

        $contacts = Contact::latest()->paginate(5);

        $stats = Contact::getStatusStats();

        return view('clubAdmin.contacts.contact.index', compact('contacts', 'breadcrumbs', 'stats'));
    }

    public function sendCustomEmail(SendCustomEmailRequest $request, Contact $contact): RedirectResponse
    {
        $mail = $request->validated();
        $user = auth()->user();

        try {
            $emailData = [
                'subject' => $mail['subject'],
                'message' => $mail['message'],
                'contact' => $contact,
                'sender_name' => $user->fullName ?? 'TTC Ottignies-Blocry',
                'club_name' => config('app.name'),
            ];

            $this->emailService->sendCustom($contact, $emailData, $user, $mail['send_copy'] ?? false);

            return redirect()
                ->route('clubAdmin.contacts.show', $contact)
                ->with('success', __('Personalized email was sent successfully'));

        } catch (Exception $e) {
            Log::error(__('Something went wrong while sending personalized email'), [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', __('Something went wrong while sending personalized email'));
        }
    }

    public function sendEmail(SendEmailRequest $request, Contact $contact): RedirectResponse
    {

        $template = $request->validated('template');

        if ($template === 'custom') {
            return redirect()->route('clubAdmin.contacts.compose-email', $contact);
        }

        try {

            $message = $this->emailService->sendTemplate($contact, $template);

            return redirect()->back()->with('success', $message);

        } catch (InvalidArgumentException) {
            // Template not managed --> to implement?
            Log::warning(__('Invalid email template'), [
                'template' => $template,
                'contact_id' => $contact->id,
            ]);

            return redirect()->back()->with('error', __('Invalid email template'));

        } catch (Exception $e) {
            Log::error(__('Something went wrong while sending email'), [
                'contact_id' => $contact->id,
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', __('Something went wrong while sending your email'));
        }
    }

    public function show(Contact $contact): View
    {
        $this->authorize('view', $contact);

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->current(__('Contact details'))
            ->toArray();

        return view('clubAdmin.contacts.contact.show', compact('contact', 'breadcrumbs'));
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());

        return redirect()->back()->with('success', __('Contact status updated successfully'));
    }
}
