<?php

declare(strict_types=1);

namespace App\Data\Tournament;

readonly class TournamentConfig
{
    public function __construct(
        public int $durationMinutes,
        public int $nbTables,
        public int $logisticsBufferMinutes,
        public int $poolSize,
        public int $nbPools,
        public int $nbQualifiersPerPool,
        public int $setsToWin,
        public string $matchType,
        public float $congestionCoefficient = 0.80,
        public bool $deuceEnabled = true,
        public bool $hasHandicapPoints = true,
    ) {}
}
