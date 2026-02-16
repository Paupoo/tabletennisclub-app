<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Contact;

use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function showForm(User $user): View
    {
        return view('clubAdmin.users.auth.invitation', compact('user'));
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // connexion directe optionnelle
        auth()->login($user);

        return redirect()->route('dashboard')->with('success', __('Welcome!'));
    }
}
