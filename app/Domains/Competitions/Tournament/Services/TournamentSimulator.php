<?php

declare(strict_types=1);

namespace App\Domains\Competitions\Tournament\Services;

use App\Data\Tournament\SimulationResult;
use App\Data\Tournament\TournamentConfig;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;

class TournamentSimulator
{
    /**
     * Average match durations (minutes) keyed by best-of count.
     * Based on observed amateur competition averages.
     *
     * @var array<int, int>
     */
    private const array MATCH_DURATIONS = [
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
        // N − 1 matches to name a winner, plus the match for third place as
        // soon as there are semi-finals.
        $bracketMatchesTotal = max(0, $finalistsCount - 1) + ($finalistsCount >= 4 ? 1 : 0);
        $grandTotalMatches = $poolMatchesTotal + $bracketMatchesTotal;
        $totalPlayers = $config->nbPools * $config->poolSize;

        $estimatedMinutes = $config->nbTables > 0
            ? ($this->poolRounds($config, $poolMatchesTotal) + $this->bracketRounds($config, $finalistsCount)) * $avgMatchMinutes
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

    /**
     * Rounds of play the bracket takes: one round waits for the previous one,
     * and the final and the match for third place are played side by side.
     */
    private function bracketRounds(TournamentConfig $config, int $finalistsCount): int
    {
        if ($finalistsCount < 2) {
            return 0;
        }

        $places = 2;
        while ($places < $finalistsCount) {
            $places *= 2;
        }

        $rounds = 0;
        for ($matches = intdiv($places, 2); $matches >= 2; $matches = intdiv($matches, 2)) {
            $rounds += (int) ceil($matches / $config->nbTables);
        }

        // The final, with the match for third place beside it when there were semi-finals.
        $rounds += (int) ceil(($places >= 4 ? 2 : 1) / $config->nbTables);

        return $rounds;
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

    /**
     * Rounds of play the pools take on these tables.
     *
     * A pool cannot go faster than its round robin — a pool of four plays
     * three rounds of two matches whatever the hall — and the hall cannot
     * hold more matches at once than it has tables.
     */
    private function poolRounds(TournamentConfig $config, int $poolMatchesTotal): int
    {
        $roundsPerPool = $config->poolSize % 2 === 0 ? $config->poolSize - 1 : $config->poolSize;

        return max($roundsPerPool, (int) ceil($poolMatchesTotal / $config->nbTables));
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
                hasHandicapPoints: false,
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
            deuceEnabled: false,
        );
    }
}
