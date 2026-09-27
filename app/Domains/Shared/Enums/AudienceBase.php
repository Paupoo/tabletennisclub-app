<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Who a club-wide message starts from, before any filter narrows it down.
 *
 * One choice only: mixing the members of the club with those who left it is
 * exactly how former members kept receiving mail.
 */
enum AudienceBase: string
{
    /** Affiliation confirmed or paid for the current season. */
    case Active = 'active';

    /** Active last season, not affiliated in any way this season. */
    case FormerMembers = 'former';

    /** Affiliation requested for the current season, not validated yet. */
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active => __('Active members'),
            self::Pending => __('Pending affiliations'),
            self::FormerMembers => __('Last season members who have not come back'),
        };
    }
}
