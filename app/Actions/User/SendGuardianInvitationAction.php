<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\Guardian;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteGuardianMail;
use App\Support\AccountProxy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Hand a parent the link that creates their account and their proxy.
 *
 * The counterpart of {@see SendInvitationAction} for a ward with no address of
 * their own. That member cannot be invited — a login sent to a guardian would
 * set a password on somebody else's account — so the club invites the guardian
 * instead: they create *their* account, and the proxy of {@see AccountProxy}
 * gives them their child's screens.
 *
 * A guardian typed by hand may turn out to be a member already: the office fills
 * in a parent's details without knowing the club has them on file. The address
 * is what identifies the person, so a match is treated as information rather
 * than as an error — the guardian sheet is linked to that account and the
 * ordinary member invitation takes over. Doing it here, at send time, is what
 * keeps the parent from discovering the clash themselves, on a unique index, in
 * the middle of a link the club just sent them.
 */
class SendGuardianInvitationAction
{
    /**
     * @return bool Whether an invitation was actually queued.
     */
    public static function handle(Guardian $guardian): bool
    {
        if ($guardian->hasAccount()) {
            return self::inviteExistingAccount($guardian);
        }

        if (blank($guardian->email)) {
            return false;
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($guardian->email))])->first();

        if ($existing instanceof User) {
            $guardian->update(['user_id' => $existing->id]);

            return self::inviteExistingAccount($guardian->refresh());
        }

        $link = URL::temporarySignedRoute(
            'guardian-invitation.accept',
            now()->addDays(User::INVITATION_LINK_VALIDITY_DAYS),
            ['guardian' => $guardian->id]
        );

        Mail::to($guardian->email)->queue(new InviteGuardianMail($guardian, $link));

        $guardian->update(['last_invited_at' => now()]);

        return true;
    }

    /**
     * A guardian who already holds a member account is invited on that account,
     * so that a single click on the ward's row does the right thing whichever
     * of the two situations the office happens to be in. One who has already
     * activated it has nothing to receive — they can act for their ward today.
     */
    private static function inviteExistingAccount(Guardian $guardian): bool
    {
        $member = $guardian->member;

        if (! $member instanceof User || $member->invitationStatus() === 'active') {
            return false;
        }

        return SendInvitationAction::handle($member);
    }
}
