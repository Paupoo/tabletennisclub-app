<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * What the federation changed about one of our fixtures.
 */
enum InterclubChangeKind: string
{
    /** A forfeit or a withdrawal now cancels the fixture; which one is on the change. */
    case FORFEIT = 'Forfeit';
    /** The federation took a forfeit back: the fixture is to be played again. */
    case FORFEIT_LIFTED = 'ForfeitLifted';
    /** A new day, time or hall. */
    case RESCHEDULED = 'Rescheduled';
}
