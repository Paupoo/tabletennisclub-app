<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Support\Breadcrumb;
use App\Support\TableBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactAdminController extends Controller
{
    public function index()
    {
        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->toArray();

        $contacts = Contact::first()->paginate(5);

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

        $breadcrumbs = Breadcrumb::make()
            ->home()
            ->contacts()
            ->current($contact->interest . ' - ' . $contact->first_name . ' ' . $contact->last_name)
            ->toArray();

        return view('admin.contacts.show', compact('contact', 'breadcrumbs'));
    }

    public function update(UpdateContactRequest $request, Contact $contact)
    {
        $validated = $request->validated();
        $contact->update($validated);

        return redirect()->back()->with('success', 'Statut mis à jour.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return redirect()->route('admin.contacts.index')->with('success', 'Contact supprimé.');
    }
}
