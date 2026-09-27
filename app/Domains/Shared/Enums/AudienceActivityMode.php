<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/** How an activity filter reads: its participants, or the ones still to answer. */
enum AudienceActivityMode: string
{
    /** Whoever a communication invited to it, and who has not registered since. */
    case InvitedNotRegistered = 'invited_not_registered';

    /** Registered, enrolled, coming — or playing in the team. */
    case Registered = 'registered';

    public function label(): string
    {
        return match ($this) {
            self::InvitedNotRegistered => __('Invited, not registered yet'),
            self::Registered => __('Registered'),
        };
    }
}
