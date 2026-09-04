<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

/**
 * One weekday of the club's usual rhythm, all its packs merged into one range.
 *
 * The dated agenda answers "what happens next"; this answers "when do you
 * generally play?" — the question a newcomer asks and a fortnight of real dates
 * cannot express. Derived from the active packs, so it can never drift from
 * them the way a hand-written line would.
 */
readonly class RhythmDay
{
    /**
     * @param  int  $dayOfWeek  ISO weekday, 1 = Monday.
     * @param  string  $startsAt  Earliest start that day, `H:i`.
     * @param  string  $endsAt  Latest end that day, `H:i`.
     */
    public function __construct(
        public int $dayOfWeek,
        public string $startsAt,
        public string $endsAt,
    ) {}
}
