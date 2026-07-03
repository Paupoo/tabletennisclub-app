<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function showForm(User $user): View|RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            return $this->redirectAlreadyActivated();
        }

        return view('clubAdmin.users.auth.invitation', compact('user'));
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        if ($user->email_verified_at !== null) {
            return $this->redirectAlreadyActivated();
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $user->markEmailAsVerified();

        auth()->login($user);

        event(new Registered($user));

        return redirect()->route('dashboard')->with('success', __('Welcome!'));
    }

    private function redirectAlreadyActivated(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', __('Your account is already activated, please log in.'));
    }
}
