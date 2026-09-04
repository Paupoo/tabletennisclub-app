<?php

declare(strict_types=1);

namespace App\Data\PublicAgenda;

/**
 * The club's habitual interclub evening, as the committee words it.
 *
 * The dated agenda already announces each home fixture, so this is not there to
 * list matches — it is the one line that answers "and when do you play
 * competition?" for someone reading the rhythm rather than the fortnight.
 * Deliberately hand-set rather than deduced: fixtures do not always land on the
 * same evening, and a day inferred week by week would wobble.
 */
readonly class InterclubRhythm
{
    /**
     * @param  string  $day  Weekday, already written in the club's words.
     * @param  string  $startsAt  When the evening opens, `H:i`.
     * @param  string  $endsAt  When it usually wraps up, `H:i`.
     */
    public function __construct(
        public string $day,
        public string $startsAt,
        public string $endsAt,
    ) {}
}
