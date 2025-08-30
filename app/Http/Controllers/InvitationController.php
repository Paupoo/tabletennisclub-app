<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InvitationController extends Controller
{
    public function showForm(User $user)
    {
        return view('auth.invitation', compact('user'));
    }

    public function store(Request $request, User $user)
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
