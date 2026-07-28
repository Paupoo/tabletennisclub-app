<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendInvitationAction
{
    /**
     * Hand a member the link that lets them set their own password.
     *
     * This is the handover of a login, not a message, so it goes to the member's
     * own address and never falls back to a guardian's — a guardian receiving it
     * would be setting a password on an account that is not theirs. A member with
     * no address of their own therefore cannot be invited yet, and saying so with
     * a `false` is what lets a bulk send count and report the ones it skipped.
     *
     * @return bool Whether an invitation was actually queued.
     */
    public static function handle(User $user): bool
    {
        if ($user->email === null) {
            return false;
        }

        $link = URL::temporarySignedRoute(
            'invitation.accept',
            now()->addDays(User::INVITATION_LINK_VALIDITY_DAYS),
            ['user' => $user->id]
        );

        Mail::to($user->email)->queue(new InviteNewUserMail($user, $link));

        $user->update(['last_invited_at' => now()]);

        return true;
    }
}
