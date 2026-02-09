<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Mail\InviteNewUserMail;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class InviteExistingUserAction
{
    /**
     * @param User $user
     * @return RedirectResponse
     */
    public static function handle(User $user): RedirectResponse
    {
        $link = URL::temporarySignedRoute(
            'invitation.accept',
            now()->addHours(48), // durée de validité
            ['user' => $user->id]);

        Mail::to($user->email)
            ->send(new InviteNewUserMail($user, $link));

        return redirect()
            ->back()
            ->with([
                'success' => __('Account created and invitation sent'),
            ]);
    }
}
