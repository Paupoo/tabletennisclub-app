<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Actions\User\SendInvitationAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    /**
     * Self-service resend from the "expired link" page. Deliberately avoids
     * implicit model binding and always returns the same response, so the
     * endpoint cannot be used to enumerate accounts.
     */
    public function resend(string $user): RedirectResponse
    {
        $invitedUser = User::find($user);

        if ($invitedUser !== null && $invitedUser->email_verified_at === null) {
            SendInvitationAction::handle($invitedUser);
        }

        return redirect()->route('login')
            ->with('status', __('If your account is awaiting activation, a new link has just been sent.'));
    }

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
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $user->markEmailAsVerified();

        auth()->login($user);

        event(new Registered($user));

        return redirect()->route('admin.user.onboarding')
            ->with('success', __('Welcome! Take a moment to complete your personal information.'));
    }

    private function redirectAlreadyActivated(): RedirectResponse
    {
        return redirect()->route('login')
            ->with('status', __('Your account is already activated, please log in.'));
    }
}
