<?php

declare(strict_types=1);

namespace App\Data\Interclub;

/**
 * One line of a division's final table.
 *
 * `teamClub` is what makes a line ours; the team name it comes with is the
 * federation's own spelling of the club plus the letter, so the letter is the
 * only part worth reading.
 */
readonly class AfttRanking
{
    public function __construct(
        public int $position,
        public string $team,
        public string $teamClub,
        public int $gamesPlayed,
        public int $gamesWon,
        public int $gamesLost,
        public int $gamesDraw,
        public int $points,
    ) {}

    /**
     * The letter this team is known by inside its club.
     */
    public function letter(): string
    {
        $words = preg_split('/\s+/', trim($this->team)) ?: [];

        return (string) end($words);
    }
}
