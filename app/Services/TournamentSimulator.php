<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Tournament\SimulationResult;
use App\Data\Tournament\TournamentConfig;
use App\Enums\TournamentObjectiveEnum;

class TournamentSimulator
{
    /**
     * Average match durations (minutes) keyed by best-of count.
     * Based on observed amateur competition averages.
     *
     * @var array<int, int>
     */
    private const MATCH_DURATIONS = [
        1 => 6,
        3 => 12,
        5 => 20,
        7 => 30,
        9 => 40,
    ];

    public function simulate(TournamentConfig $config): SimulationResult
    {
        $bestOf = ($config->setsToWin * 2) - 1;
        $baseDuration = self::MATCH_DURATIONS[$bestOf] ?? 12;
        $doublesMultiplier = $config->matchType === 'double' ? 1.20 : 1.0;
        $avgMatchMinutes = (int) ceil(
            ($baseDuration * $doublesMultiplier) + $config->logisticsBufferMinutes
        );

        $totalMatchCapacity = $config->nbTables > 0
            ? (int) floor(($config->durationMinutes / $avgMatchMinutes) * $config->nbTables * $config->congestionCoefficient)
            : 0;

        $matchesPerPool = (int) ($config->poolSize * ($config->poolSize - 1) / 2);
        $poolMatchesTotal = $matchesPerPool * $config->nbPools;
        $finalistsCount = $config->nbPools * $config->nbQualifiersPerPool;
        $bracketMatchesTotal = max(0, $finalistsCount - 1);
        $grandTotalMatches = $poolMatchesTotal + $bracketMatchesTotal;
        $totalPlayers = $config->nbPools * $config->poolSize;

        $estimatedMinutes = $config->nbTables > 0
            ? (int) (ceil($grandTotalMatches / $config->nbTables) * $avgMatchMinutes)
            : 0;

        $tableOccupancyPercent = $totalMatchCapacity > 0
            ? (int) round(($grandTotalMatches / $totalMatchCapacity) * 100)
            : 0;

        $avgMatchesPerPlayer = $totalPlayers > 0
            ? round($grandTotalMatches / $totalPlayers, 1)
            : 0.0;

        $safetyMarginMatches = $totalMatchCapacity - $grandTotalMatches;

        // Estimated wait time: how long a player waits between matches on average.
        // Approximation: total time / matches_per_player - avg_match_duration
        $avgWaitTimeMinutes = $avgMatchesPerPlayer > 0
            ? max(0, (int) round(($estimatedMinutes / $avgMatchesPerPlayer) - $avgMatchMinutes))
            : 0;

        $riskLevel = match (true) {
            $tableOccupancyPercent > 100 => 'danger',
            $tableOccupancyPercent >= 80 => 'warning',
            default => 'ok',
        };

        return new SimulationResult(
            totalPlayers: $totalPlayers,
            grandTotalMatches: $grandTotalMatches,
            poolMatchesTotal: $poolMatchesTotal,
            bracketMatchesTotal: $bracketMatchesTotal,
            finalistsCount: $finalistsCount,
            totalMatchCapacity: $totalMatchCapacity,
            avgMatchMinutes: $avgMatchMinutes,
            estimatedMinutes: $estimatedMinutes,
            tableOccupancyPercent: $tableOccupancyPercent,
            avgMatchesPerPlayer: $avgMatchesPerPlayer,
            safetyMarginMatches: $safetyMarginMatches,
            avgWaitTimeMinutes: $avgWaitTimeMinutes,
            riskLevel: $riskLevel,
            isFeasible: $grandTotalMatches <= $totalMatchCapacity,
        );
    }

    /**
     * Suggest an optimal configuration for the given physical constraints and objective.
     * Explores the configuration space and returns the best match.
     */
    public function suggestOptimalConfig(
        int $durationMinutes,
        int $nbTables,
        TournamentObjectiveEnum $objective,
        float $congestionCoefficient = 0.80,
    ): TournamentConfig {
        return match ($objective) {
            TournamentObjectiveEnum::MaximizePlayers => $this->suggestMaximizePlayers($durationMinutes, $nbTables, $congestionCoefficient),
            TournamentObjectiveEnum::MinimizeDuration => $this->suggestMinimizeDuration($durationMinutes, $nbTables, $congestionCoefficient),
            TournamentObjectiveEnum::MaximizeMatchesPerPlayer => $this->suggestMaximizeMatchesPerPlayer($durationMinutes, $nbTables, $congestionCoefficient),
            TournamentObjectiveEnum::Leisure => $this->suggestLeisure($durationMinutes, $nbTables, $congestionCoefficient),
            TournamentObjectiveEnum::Competitive => $this->suggestCompetitive($durationMinutes, $nbTables, $congestionCoefficient),
        };
    }

    private function defaultConfig(int $durationMinutes, int $nbTables, float $congestion): TournamentConfig
    {
        return new TournamentConfig(
            durationMinutes: $durationMinutes,
            nbTables: $nbTables,
            logisticsBufferMinutes: 3,
            poolSize: 4,
            nbPools: 4,
            nbQualifiersPerPool: 2,
            setsToWin: 3,
            matchType: 'single',
            congestionCoefficient: $congestion,
        );
    }

    private function suggestCompetitive(int $durationMinutes, int $nbTables, float $congestion): TournamentConfig
    {
        // Standard competitive: BO5, pools of 4, 2 qualifiers.
        $best = null;
        $bestPlayers = 0;

        for ($nbPools = 2; $nbPools <= 16; $nbPools++) {
            $config = new TournamentConfig(
                durationMinutes: $durationMinutes,
                nbTables: $nbTables,
                logisticsBufferMinutes: 3,
                poolSize: 4,
                nbPools: $nbPools,
                nbQualifiersPerPool: 2,
                setsToWin: 3,
                matchType: 'single',
                congestionCoefficient: $congestion,
            );

            $result = $this->simulate($config);

            if ($result->isFeasible && $result->totalPlayers > $bestPlayers) {
                $bestPlayers = $result->totalPlayers;
                $best = $config;
            }
        }

        return $best ?? $this->defaultConfig($durationMinutes, $nbTables, $congestion);
    }

    private function suggestLeisure(int $durationMinutes, int $nbTables, float $congestion): TournamentConfig
    {
        // Relaxed: BO3, pools of 4, generous buffer.
        return new TournamentConfig(
            durationMinutes: $durationMinutes,
            nbTables: $nbTables,
            logisticsBufferMinutes: 5,
            poolSize: 4,
            nbPools: max(2, (int) floor($nbTables / 1.5)),
            nbQualifiersPerPool: 2,
            setsToWin: 3,
            matchType: 'single',
            congestionCoefficient: $congestion,
        );
    }

    private function suggestMaximizeMatchesPerPlayer(int $durationMinutes, int $nbTables, float $congestion): TournamentConfig
    {
        // Larger pools (5-6 players) so each player plays more pool matches.
        $best = null;
        $bestAvg = 0.0;

        foreach ([5, 6] as $poolSize) {
            foreach ([2, 3] as $setsToWin) {
                for ($nbPools = 2; $nbPools <= 12; $nbPools++) {
                    $config = new TournamentConfig(
                        durationMinutes: $durationMinutes,
                        nbTables: $nbTables,
                        logisticsBufferMinutes: 3,
                        poolSize: $poolSize,
                        nbPools: $nbPools,
                        nbQualifiersPerPool: 2,
                        setsToWin: $setsToWin,
                        matchType: 'single',
                        congestionCoefficient: $congestion,
                    );

                    $result = $this->simulate($config);

                    if ($result->isFeasible && $result->avgMatchesPerPlayer > $bestAvg) {
                        $bestAvg = $result->avgMatchesPerPlayer;
                        $best = $config;
                    }
                }
            }
        }

        return $best ?? $this->defaultConfig($durationMinutes, $nbTables, $congestion);
    }

    private function suggestMaximizePlayers(int $durationMinutes, int $nbTables, float $congestion): TournamentConfig
    {
        // Small pools (3-4 players) to reduce matches per pool, maximize headcount.
        $best = null;
        $bestPlayers = 0;

        foreach ([3, 4] as $poolSize) {
            foreach ([2, 3] as $setsToWin) {
                for ($nbPools = 2; $nbPools <= 20; $nbPools++) {
                    $config = new TournamentConfig(
                        durationMinutes: $durationMinutes,
                        nbTables: $nbTables,
                        logisticsBufferMinutes: 3,
                        poolSize: $poolSize,
                        nbPools: $nbPools,
                        nbQualifiersPerPool: 2,
                        setsToWin: $setsToWin,
                        matchType: 'single',
                        congestionCoefficient: $congestion,
                    );

                    $result = $this->simulate($config);

                    if ($result->isFeasible && $result->totalPlayers > $bestPlayers) {
                        $bestPlayers = $result->totalPlayers;
                        $best = $config;
                    }
                }
            }
        }

        return $best ?? $this->defaultConfig($durationMinutes, $nbTables, $congestion);
    }

    private function suggestMinimizeDuration(int $durationMinutes, int $nbTables, float $congestion): TournamentConfig
    {
        // Few small pools + BO3, maximize parallel play.
        return new TournamentConfig(
            durationMinutes: $durationMinutes,
            nbTables: $nbTables,
            logisticsBufferMinutes: 3,
            poolSize: 3,
            nbPools: max(2, (int) floor($nbTables / 2)),
            nbQualifiersPerPool: 1,
            setsToWin: 2,
            matchType: 'single',
            congestionCoefficient: $congestion,
        );
    }
}
