<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

use Carbon\CarbonImmutable;

/**
 * One calendar day of the public agenda.
 *
 * A day with nothing on it is never built: the agenda skips straight from
 * Saturday to Monday rather than rendering empty columns.
 */
readonly class AgendaDay
{
    /**
     * @param  CarbonImmutable  $date  The day itself, at midnight.
     * @param  list<AgendaEntry>  $entries  Sorted by start time, never empty.
     */
    public function __construct(
        public CarbonImmutable $date,
        public array $entries,
    ) {}
}
