<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Http\Requests\StoreUserRequest;
use App\Mail\InviteNewUserMail;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class CreateNewUserAction
{
    // TODO : Il est interdit d'utiliser des Request dans les Actions,(que des types 'simples'), voir comment implémenter autrement (DTO?)
    /**
     * @param StoreUserRequest $request
     * @return RedirectResponse
     */
    public static function handle(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($request->validated());

        $link = URL::temporarySignedRoute(
            'invitation.accept',
            now()->addHours(48), // durée de validité
            ['user' => $user->id]);

        Mail::to($user->email)
            ->send(new InviteNewUserMail($user, $link));

        // Mail::to($user->email)->send(new InviteNewUserMail($user, $request->password));

        return redirect()
            ->back()
            ->with([
                'success' => __('Account created and invitation sent'),
            ]);
    }
}
