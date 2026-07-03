<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendInvitationAction
{
    public static function handle(User $user): void
    {
        $link = URL::temporarySignedRoute(
            'invitation.accept',
            now()->addDays(User::INVITATION_LINK_VALIDITY_DAYS),
            ['user' => $user->id]
        );

        Mail::to($user->email)->queue(new InviteNewUserMail($user, $link));

        $user->update(['last_invited_at' => now()]);
    }
}
