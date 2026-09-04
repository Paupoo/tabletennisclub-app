<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

use Carbon\CarbonImmutable;

/**
 * One square of the month grid.
 *
 * Every day of the window gets one, empty ones included: the grid draws the
 * club's week, and a missing Thursday would break the shape that makes the
 * rhythm readable at a glance.
 */
readonly class AgendaDay
{
    /**
     * @param  CarbonImmutable  $date  The day itself, at midnight.
     * @param  list<AgendaEntry>  $entries  Sorted by start time; may be empty.
     * @param  bool  $isToday  Drawn with the club's accent.
     * @param  bool  $isPast  Dimmed: the grid starts on Monday, so the first
     *                        days of the current week are already behind us.
     */
    public function __construct(
        public CarbonImmutable $date,
        public array $entries,
        public bool $isToday = false,
        public bool $isPast = false,
    ) {}
}
