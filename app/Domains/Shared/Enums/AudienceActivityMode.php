<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * How an activity filter reads: its participants, the ones still to invite,
 * or the ones invited who have not answered. The last two never overlap, so
 * nobody receives the same invitation twice.
 */
enum AudienceActivityMode: string
{
    /** Whoever a communication invited to it, and who has not registered since. */
    case InvitedNotRegistered = 'invited_not_registered';

    /** Neither invited by a communication yet, nor registered on their own. */
    case NotInvited = 'not_invited';

    /** Registered, enrolled, coming — or playing in the team. */
    case Registered = 'registered';

    public function label(): string
    {
        return match ($this) {
            self::InvitedNotRegistered => __('Invited, not registered yet'),
            self::NotInvited => __('Not invited yet'),
            self::Registered => __('Registered for it'),
        };
    }
}
