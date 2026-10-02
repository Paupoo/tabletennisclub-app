<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Domains\ClubAdmin\Users\Models\MemberDeparture;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;

/**
 * Take back a departure recorded by mistake.
 *
 * Only the record goes: the team places, the captaincies and the places in
 * the interclub matches to come that the departure handed back are not
 * restored, since somebody may already have taken them. The
 * training sessions and the mailings read the departure itself, so the member
 * is back in both as soon as it is gone.
 */
class CancelMemberDepartureAction
{
    public static function handle(User $user): void
    {
        MemberDeparture::query()
            ->where('user_id', $user->id)
            ->where('season_id', Season::current()->id ?? 0)
            ->delete();

        $user->unsetRelation('departureThisSeason');
    }
}
