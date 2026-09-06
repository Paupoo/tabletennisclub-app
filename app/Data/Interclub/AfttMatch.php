<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use Carbon\CarbonImmutable;

/**
 * One fixture as the federation publishes it.
 *
 * `matchId` is the only stable handle TabT offers: `MatchUniqueId` comes back
 * empty on every row of every division we play in, so it cannot be used. The
 * cost is that `matchId` embeds the round (`PBBWH05/114`), so a division
 * recomputed after a withdrawal reissues its identifiers wholesale.
 *
 * `weekName` is the round, not a calendar week, and it is scoped to the
 * category: men play 18 rounds where veterans play 7, so "round 05" is a
 * different date in each. It must never be confused with our own
 * `interclubs.week_number`, which is the ISO calendar week and carries the
 * "already playing that week" rule across categories.
 *
 * A bye has no opponent, no date and no venue. TabT writes it as the club `-`
 * and the team name `'Bye '` — with the trailing space.
 */
readonly class AfttMatch
{
    public function __construct(
        public string $matchId,
        public string $weekName,
        public ?CarbonImmutable $date,
        public ?string $time,
        public string $homeClub,
        public string $homeTeam,
        public string $awayClub,
        public string $awayTeam,
        public int $divisionId,
        public string $divisionName,
        public int $divisionCategory,
        public ?AfttVenue $venue,
        public bool $isBye,
    ) {}
}
