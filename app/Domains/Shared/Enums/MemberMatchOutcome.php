<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What the club roster had to say about one affiliate in the federation listing.
 *
 * Only {@see self::NEW} and {@see self::MATCHED} are conclusions the import may
 * act on unattended. The other two are questions put to a human: they name a
 * candidate without committing to it.
 */
enum MemberMatchOutcome: string
{
    /** A member is archived under this identity — restore rather than duplicate? */
    case ARCHIVED = 'archived';

    /** Same licence, same address, or same name and birthdate. */
    case MATCHED = 'matched';

    /** Nobody on the roster answers to this identity. */
    case NEW = 'new';

    /** Same name, different birthdate — a namesake, or a wrong date somewhere. */
    case SUSPECT = 'suspect';
}
