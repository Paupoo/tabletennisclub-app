<?php

declare(strict_types=1);

namespace App\Data\Interclub;

use Carbon\CarbonImmutable;

/**
 * One match one of our members played in an official tournament.
 *
 * Read from a member's own result list, so it is already written from our
 * side: the sets are theirs first, and the verdict is theirs. The federation
 * gives it no identifier of any kind — no match id, no tournament id — which
 * is why nothing downstream can key on it.
 */
readonly class AfttTournamentResult
{
    public function __construct(
        public string $playerLicence,
        public string $playerName,
        public ?string $playerRanking,
        public CarbonImmutable $playedOn,
        public string $tournamentName,
        public ?string $serieName,
        public ?string $opponentLicence,
        public string $opponentName,
        public ?string $opponentRanking,
        public ?string $opponentClub,
        public ?int $ourSets,
        public ?int $theirSets,
        public bool $won,
    ) {}
}
