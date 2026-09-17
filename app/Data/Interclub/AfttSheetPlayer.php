<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * One player as a match sheet lists them.
 *
 * `uniqueIndex` is the federation's licence index, and it is the only handle on
 * identity a sheet offers: no club code, no birth date. It is what our own
 * `users.licence` holds, so it is what the import joins on.
 *
 * `victoryCount` is read but never trusted for a total. The federation leaves it
 * out for a player who forfeited, and it never counts the double — in a
 * system-4 tie the three home counts always fall one short of the team score.
 * Wins are derived from the individual results instead.
 */
readonly class AfttSheetPlayer
{
    public function __construct(
        public int $position,
        public string $uniqueIndex,
        public string $firstName,
        public string $lastName,
        public ?string $ranking,
        public ?int $victoryCount,
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }
}
