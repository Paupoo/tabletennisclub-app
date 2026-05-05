<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubAdmin\Users\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Club;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('clubAdmin.users.auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'last_name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        Log::info('Registration request validated', ['email' => $request->email]);

        $user = User::make([
            'last_name' => $request->last_name,
            'first_name' => $request->first_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->club()->associate(Club::firstWhere('licence', config('app.club_licence')));
        $user->save();

        event(new Registered($user));

        Auth::guard('web')->login($user);
        // Ensure session is regenerated so authentication persists in tests
        session()->regenerate();

        return redirect(RouteServiceProvider::HOME);
    }
}
