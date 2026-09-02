<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * A division as the federation classifies it.
 *
 * Fetched for one reason: `level` appears nowhere else. Neither `GetClubTeams`
 * nor `GetMatches` carries it, and the level is half of what identifies a league
 * on our side. Reading it from the printed name instead would mean parsing
 * "Prov. B.B.W." out of a French label that the federation is free to rewrite —
 * so the division list is fetched once and the numeric codes are used.
 */
readonly class AfttDivision
{
    public function __construct(
        public int $id,
        public string $name,
        public int $category,
        public int $level,
    ) {}
}
