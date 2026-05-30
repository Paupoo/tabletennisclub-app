<?php

declare(strict_types=1);

use App\Data\Tournament\TournamentConfig;
use App\Domains\Shared\Enums\TournamentObjectiveEnum;
use App\Services\TournamentSimulator;

function makeConfig(array $overrides = []): TournamentConfig
{
    return new TournamentConfig(
        durationMinutes: $overrides['durationMinutes'] ?? 240,
        nbTables: $overrides['nbTables'] ?? 4,
        logisticsBufferMinutes: $overrides['logisticsBufferMinutes'] ?? 3,
        poolSize: $overrides['poolSize'] ?? 4,
        nbPools: $overrides['nbPools'] ?? 4,
        nbQualifiersPerPool: $overrides['nbQualifiersPerPool'] ?? 2,
        setsToWin: $overrides['setsToWin'] ?? 3,
        matchType: $overrides['matchType'] ?? 'single',
        congestionCoefficient: $overrides['congestionCoefficient'] ?? 0.80,
    );
}

// ── Player & match counts ─────────────────────────────────────────────────────

it('calculates total players correctly', function (): void {
    // 4 pools × 4 players = 16
    $result = (new TournamentSimulator)->simulate(makeConfig());
    expect($result->totalPlayers)->toBe(16);
});

it('calculates pool matches total correctly', function (): void {
    // 4 pools × C(4,2) = 4 × 6 = 24
    $result = (new TournamentSimulator)->simulate(makeConfig());
    expect($result->poolMatchesTotal)->toBe(24);
});

it('calculates bracket matches correctly', function (): void {
    // finalists = 4 × 2 = 8 → bracket = 7 → grand total = 31
    $result = (new TournamentSimulator)->simulate(makeConfig());
    expect($result->finalistsCount)->toBe(8);
    expect($result->bracketMatchesTotal)->toBe(7);
    expect($result->grandTotalMatches)->toBe(31);
});

it('calculates correct pool matches for pools of 3', function (): void {
    // 2 pools × C(3,2) = 2 × 3 = 6
    $result = (new TournamentSimulator)->simulate(makeConfig(['poolSize' => 3, 'nbPools' => 2]));
    expect($result->poolMatchesTotal)->toBe(6);
});

// ── avgMatchMinutes ───────────────────────────────────────────────────────────

it('applies correct duration for setsToWin=3 (bestOf 5)', function (): void {
    // bestOf=5 → base=20, buffer=3 → avg=23
    $result = (new TournamentSimulator)->simulate(makeConfig(['setsToWin' => 3]));
    expect($result->avgMatchMinutes)->toBe(23);
});

it('applies correct duration for setsToWin=2 (bestOf 3)', function (): void {
    // bestOf=3 → base=12, buffer=3 → avg=15
    $result = (new TournamentSimulator)->simulate(makeConfig(['setsToWin' => 2]));
    expect($result->avgMatchMinutes)->toBe(15);
});

it('applies doubles multiplier (1.20) to match duration', function (): void {
    // setsToWin=3 → base=20, ×1.20=24, +buffer 3 = 27
    $result = (new TournamentSimulator)->simulate(makeConfig(['matchType' => 'double']));
    expect($result->avgMatchMinutes)->toBe(27);
});

// ── risk levels ───────────────────────────────────────────────────────────────

it('returns riskLevel ok when occupancy is below 80%', function (): void {
    // 2 pools × 3 players, setsToWin=2, 240 min, 4 tables → very low occupancy
    $result = (new TournamentSimulator)->simulate(makeConfig([
        'poolSize' => 3,
        'nbPools' => 2,
        'setsToWin' => 2,
        'nbQualifiersPerPool' => 1,
    ]));
    expect($result->riskLevel)->toBe('ok');
    expect($result->tableOccupancyPercent)->toBeLessThan(80);
});

it('returns riskLevel warning when occupancy is between 80% and 100%', function (): void {
    // 4 pools × 4 players, setsToWin=3, 240 min, 4 tables → ~94%
    $result = (new TournamentSimulator)->simulate(makeConfig());
    expect($result->riskLevel)->toBe('warning');
    expect($result->tableOccupancyPercent)->toBeGreaterThanOrEqual(80);
    expect($result->tableOccupancyPercent)->toBeLessThanOrEqual(100);
});

it('returns riskLevel danger when occupancy exceeds 100%', function (): void {
    // 8 pools × 5 players, setsToWin=3, 120 min, 2 tables → impossible
    $result = (new TournamentSimulator)->simulate(makeConfig([
        'poolSize' => 5,
        'nbPools' => 8,
        'durationMinutes' => 120,
        'nbTables' => 2,
    ]));
    expect($result->riskLevel)->toBe('danger');
    expect($result->tableOccupancyPercent)->toBeGreaterThan(100);
});

// ── feasibility ───────────────────────────────────────────────────────────────

it('marks tournament as feasible when matches fit within capacity', function (): void {
    $result = (new TournamentSimulator)->simulate(makeConfig([
        'poolSize' => 3,
        'nbPools' => 2,
        'setsToWin' => 2,
        'nbQualifiersPerPool' => 1,
    ]));
    expect($result->isFeasible)->toBeTrue();
});

it('marks tournament as not feasible when matches exceed capacity', function (): void {
    $result = (new TournamentSimulator)->simulate(makeConfig([
        'poolSize' => 5,
        'nbPools' => 8,
        'durationMinutes' => 120,
        'nbTables' => 2,
    ]));
    expect($result->isFeasible)->toBeFalse();
});

// ── edge cases ────────────────────────────────────────────────────────────────

it('returns zero occupancy when nbTables is zero', function (): void {
    $result = (new TournamentSimulator)->simulate(makeConfig(['nbTables' => 0]));
    expect($result->tableOccupancyPercent)->toBe(0);
    expect($result->totalMatchCapacity)->toBe(0);
});

it('safetyMarginMatches equals capacity minus total matches', function (): void {
    $result = (new TournamentSimulator)->simulate(makeConfig());
    expect($result->safetyMarginMatches)->toBe($result->totalMatchCapacity - $result->grandTotalMatches);
});

// ── suggestOptimalConfig ──────────────────────────────────────────────────────

it('returns a TournamentConfig for MaximizePlayers objective', function (): void {
    $config = (new TournamentSimulator)->suggestOptimalConfig(240, 6, TournamentObjectiveEnum::MaximizePlayers);
    expect($config)->toBeInstanceOf(TournamentConfig::class);
    expect($config->nbPools * $config->poolSize)->toBeGreaterThan(0);
});

it('returns a TournamentConfig for MinimizeDuration objective with setsToWin=2', function (): void {
    $config = (new TournamentSimulator)->suggestOptimalConfig(240, 6, TournamentObjectiveEnum::MinimizeDuration);
    expect($config)->toBeInstanceOf(TournamentConfig::class);
    expect($config->setsToWin)->toBe(2);
});

it('returns a TournamentConfig for MaximizeMatchesPerPlayer with poolSize >= 5', function (): void {
    $config = (new TournamentSimulator)->suggestOptimalConfig(240, 6, TournamentObjectiveEnum::MaximizeMatchesPerPlayer);
    expect($config)->toBeInstanceOf(TournamentConfig::class);
    expect($config->poolSize)->toBeGreaterThanOrEqual(5);
});

it('returns a TournamentConfig for Leisure with generous buffer', function (): void {
    $config = (new TournamentSimulator)->suggestOptimalConfig(240, 6, TournamentObjectiveEnum::Leisure);
    expect($config)->toBeInstanceOf(TournamentConfig::class);
    expect($config->logisticsBufferMinutes)->toBeGreaterThan(3);
});

it('returns a TournamentConfig for Competitive with setsToWin=3', function (): void {
    $config = (new TournamentSimulator)->suggestOptimalConfig(240, 6, TournamentObjectiveEnum::Competitive);
    expect($config)->toBeInstanceOf(TournamentConfig::class);
    expect($config->setsToWin)->toBe(3);
});

it('MaximizePlayers suggested config produces a feasible simulation', function (): void {
    $config = (new TournamentSimulator)->suggestOptimalConfig(240, 6, TournamentObjectiveEnum::MaximizePlayers);
    $result = (new TournamentSimulator)->simulate($config);
    expect($result->isFeasible)->toBeTrue();
});
