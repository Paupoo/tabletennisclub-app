<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Http\Requests\StoreUserRequest;
use App\Mail\InviteNewUserMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class InviteExistingUserAction
{
    public static function handle(User $user)
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
                'success' => __('Invitation sent to ', ['name' => $user->full_name]),
            ]);
    }
}
