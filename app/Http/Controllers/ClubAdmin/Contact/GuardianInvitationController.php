<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Gender;
use App\Domains\Shared\Rules\ValidPhone;
use App\Http\Controllers\Controller;
use App\Support\AccountProxy;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Where a parent turns the club's invitation into an account and a proxy.
 *
 * The link is signed and short-lived like a member's, and single-use by
 * construction rather than by a flag: once `guardians.user_id` is filled there
 * is nothing left for it to create, and {@see self::redirectAlreadyLinked()}
 * sends the second click to the login page.
 *
 * It is also worth more than a member's invitation — it opens a minor's file,
 * not the recipient's own — which is why the form asks for a password rather
 * than signing the visitor straight in.
 */
class GuardianInvitationController extends Controller
{
    public function showForm(Guardian $guardian): View|RedirectResponse
    {
        if ($guardian->hasAccount()) {
            return $this->redirectAlreadyLinked();
        }

        return view('clubAdmin.users.auth.guardian-invitation', [
            'guardian' => $guardian,
            'wards' => $guardian->users()->orderBy('first_name')->get(),
        ]);
    }

    public function store(Request $request, Guardian $guardian): RedirectResponse
    {
        if ($guardian->hasAccount()) {
            return $this->redirectAlreadyLinked();
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'string', 'max:255', new ValidPhone],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // The sheet is the club's copy of the same person, so a correction made
        // here is a correction of the club's records, not only of the account.
        $guardian->update([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'] ?? $guardian->phone,
        ]);

        $parent = User::create([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $guardian->email,
            'gender' => $validated['gender'],
            'phone_number' => $validated['phone'] ?? $guardian->phone,
            'iban' => $guardian->iban,
            'password' => Hash::make($validated['password']),
        ]);

        // The signed link was delivered to this address, so it is proven; and an
        // account that still had to verify it would be bounced by the `verified`
        // middleware before reaching the child it was created for.
        $parent->markEmailAsVerified();

        $guardian->update(['user_id' => $parent->id]);

        Auth::login($parent);

        event(new Registered($parent));

        $wards = $guardian->users()->orderBy('first_name')->get();

        // One ward: the parent came here for that account, so hand it to them.
        // Several: the choice is theirs, and the switcher in the menu makes it.
        if ($wards->count() === 1 && $parent->mayActFor($wards->first())) {
            AccountProxy::start($wards->first());

            return redirect()->route('dashboard')
                ->with('success', __('Welcome! You are now managing :name\'s account.', ['name' => $wards->first()->first_name]));
        }

        return redirect()->route('dashboard')
            ->with('success', __('Welcome! Choose from the menu the account you wish to manage.'));
    }

    private function redirectAlreadyLinked(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', __('Your account is already activated, please log in.'));
    }
}
