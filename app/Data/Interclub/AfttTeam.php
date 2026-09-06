<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * One of the club's own teams, as the federation registered it.
 *
 * The letter is the whole identity on our side: a team is "Men C" or
 * "Veterans A", and the division it plays in is what changes from season to
 * season. That is why the federation's own `TeamId` (`9756-3`, a division plus a
 * slot) is not kept — it carries no meaning the club would recognise, and it
 * changes when a division is recomputed.
 */
readonly class AfttTeam
{
    public function __construct(
        public string $letter,
        public int $divisionId,
        public string $divisionName,
        public int $divisionCategory,
    ) {}
}
