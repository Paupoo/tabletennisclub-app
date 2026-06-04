<?php

declare(strict_types=1);

namespace App\Data\Tournament;

readonly class SimulationResult
{
    public function __construct(
        public int $totalPlayers,
        public int $grandTotalMatches,
        public int $poolMatchesTotal,
        public int $bracketMatchesTotal,
        public int $finalistsCount,
        public int $totalMatchCapacity,
        public int $avgMatchMinutes,
        public int $estimatedMinutes,
        public int $tableOccupancyPercent,
        public float $avgMatchesPerPlayer,
        public int $safetyMarginMatches,
        public int $avgWaitTimeMinutes,
        public string $riskLevel,
        public bool $isFeasible,
    ) {}
}
