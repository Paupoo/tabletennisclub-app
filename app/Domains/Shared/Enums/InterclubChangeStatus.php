<?php

declare(strict_types=1);

namespace App\Domains\Shared\Enums;

/**
 * Where the team's notification of a federation change stands.
 */
enum InterclubChangeStatus: string
{
    /** Reviewed and deliberately not sent. */
    case DISMISSED = 'Dismissed';
    /** Part of a batch too large to trust: waits for somebody to check it. */
    case HELD = 'Held';
    /** To be sent on this run. */
    case PENDING = 'Pending';
    /** The team and the captain have been told. */
    case SENT = 'Sent';
    /** Recorded, never to be sent: the fixture had started, or the run was silent. */
    case SILENT = 'Silent';
}
