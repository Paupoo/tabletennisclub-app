<?php

declare(strict_types=1);

use App\Data\Tournament\TournamentConfig;
use App\Enums\TournamentObjectiveEnum;
use App\Services\TournamentSimulator;

beforeEach(function () {
    $this->simulator = new TournamentSimulator;
});

// ── helpers ──────────────────────────────────────────────────────────────────

function defaultConfig(array $overrides = []): TournamentConfig
{
    return new TournamentConfig(
        durationMinutes: $overrides['durationMinutes'] ?? 180,
        nbTables: $overrides['nbTables'] ?? 8,
        logisticsBufferMinutes: $overrides['logisticsBufferMinutes'] ?? 3,
        poolSize: $overrides['poolSize'] ?? 4,
        nbPools: $overrides['nbPools'] ?? 4,
        nbQualifiersPerPool: $overrides['nbQualifiersPerPool'] ?? 2,
        setsToWin: $overrides['setsToWin'] ?? 3,
        matchType: $overrides['matchType'] ?? 'single',
        congestionCoefficient: $overrides['congestionCoefficient'] ?? 0.80,
    );
}

// ── Formules combinatoires ────────────────────────────────────────────────────

it('calculates pool matches as n(n-1)/2', function (int $poolSize, int $expected) {
    $result = $this->simulator->simulate(defaultConfig(['poolSize' => $poolSize, 'nbPools' => 1]));

    expect($result->poolMatchesTotal)->toBe($expected);
})->with([
    'pool of 3' => [3, 3],
    'pool of 4' => [4, 6],
    'pool of 5' => [5, 10],
    'pool of 6' => [6, 15],
]);

it('calculates bracket matches as N-1', function (int $nbPools, int $nbQualifiers, int $expected) {
    $result = $this->simulator->simulate(defaultConfig([
        'nbPools' => $nbPools,
        'nbQualifiersPerPool' => $nbQualifiers,
    ]));

    expect($result->bracketMatchesTotal)->toBe($expected);
})->with([
    '4 pools x 2 qualifiers = 8 finalists -> 7 matches' => [4, 2, 7],
    '3 pools x 1 qualifier = 3 finalists -> 2 matches' => [3, 1, 2],
    '8 pools x 2 qualifiers = 16 finalists -> 15 matches' => [8, 2, 15],
]);

it('calculates total players as nbPools x poolSize', function () {
    $result = $this->simulator->simulate(defaultConfig(['nbPools' => 4, 'poolSize' => 5]));

    expect($result->totalPlayers)->toBe(20);
});

it('calculates grand total matches as pool + bracket', function () {
    // 4 pools x 4 players = 6 matches/pool x 4 = 24 pool matches
    // 4 pools x 2 qualifiers = 8 finalists -> 7 bracket matches
    $result = $this->simulator->simulate(defaultConfig());

    expect($result->poolMatchesTotal)->toBe(24)
        ->and($result->bracketMatchesTotal)->toBe(7)
        ->and($result->grandTotalMatches)->toBe(31);
});

// ── Duree des matchs ─────────────────────────────────────────────────────────

it('adds 20% duration for doubles', function () {
    $single = $this->simulator->simulate(defaultConfig(['matchType' => 'single', 'logisticsBufferMinutes' => 0]));
    $double = $this->simulator->simulate(defaultConfig(['matchType' => 'double', 'logisticsBufferMinutes' => 0]));

    expect($double->avgMatchMinutes)->toBeGreaterThan($single->avgMatchMinutes);
});

it('adds logistics buffer to avg match minutes', function () {
    $withBuffer = $this->simulator->simulate(defaultConfig(['logisticsBufferMinutes' => 5]));
    $withoutBuffer = $this->simulator->simulate(defaultConfig(['logisticsBufferMinutes' => 0]));

    expect($withBuffer->avgMatchMinutes)->toBe($withoutBuffer->avgMatchMinutes + 5);
});

// ── Capacite & faisabilite ────────────────────────────────────────────────────

it('applies congestion coefficient to total match capacity', function () {
    $full = $this->simulator->simulate(defaultConfig(['congestionCoefficient' => 1.0]));
    $congested = $this->simulator->simulate(defaultConfig(['congestionCoefficient' => 0.80]));

    expect($congested->totalMatchCapacity)->toBeLessThan($full->totalMatchCapacity);
});

it('flags tournament as feasible when matches fit in capacity', function () {
    $result = $this->simulator->simulate(defaultConfig([
        'durationMinutes' => 300,
        'nbTables' => 10,
        'nbPools' => 2,
        'poolSize' => 3,
    ]));

    expect($result->isFeasible)->toBeTrue()
        ->and($result->riskLevel)->toBe('ok');
});

it('flags tournament as not feasible when capacity is exceeded', function () {
    $result = $this->simulator->simulate(defaultConfig([
        'durationMinutes' => 30,
        'nbTables' => 1,
        'nbPools' => 10,
        'poolSize' => 6,
    ]));

    expect($result->isFeasible)->toBeFalse()
        ->and($result->riskLevel)->toBe('danger');
});

it('calculates safety margin as capacity minus total matches', function () {
    $result = $this->simulator->simulate(defaultConfig([
        'durationMinutes' => 300,
        'nbTables' => 10,
    ]));

    expect($result->safetyMarginMatches)
        ->toBe($result->totalMatchCapacity - $result->grandTotalMatches);
});

it('returns zero capacity and zero estimated minutes when nb tables is zero', function () {
    $result = $this->simulator->simulate(defaultConfig(['nbTables' => 0]));

    expect($result->totalMatchCapacity)->toBe(0)
        ->and($result->estimatedMinutes)->toBe(0);
});

// ── Suggestions ───────────────────────────────────────────────────────────────

it('suggest maximize players returns feasible config with players', function () {
    $config = $this->simulator->suggestOptimalConfig(180, 8, TournamentObjectiveEnum::MaximizePlayers);
    $result = $this->simulator->simulate($config);

    expect($result->isFeasible)->toBeTrue()
        ->and($result->totalPlayers)->toBeGreaterThan(0);
});

it('suggest competitive returns feasible config with sets to win 3', function () {
    $config = $this->simulator->suggestOptimalConfig(240, 10, TournamentObjectiveEnum::Competitive);
    $result = $this->simulator->simulate($config);

    expect($result->isFeasible)->toBeTrue()
        ->and($config->setsToWin)->toBe(3);
});

it('suggest maximize matches per player returns feasible config with large pools', function () {
    $config = $this->simulator->suggestOptimalConfig(240, 10, TournamentObjectiveEnum::MaximizeMatchesPerPlayer);
    $result = $this->simulator->simulate($config);

    expect($result->isFeasible)->toBeTrue()
        ->and($config->poolSize)->toBeGreaterThanOrEqual(5);
});

it('suggest leisure uses extended logistics buffer', function () {
    $config = $this->simulator->suggestOptimalConfig(180, 8, TournamentObjectiveEnum::Leisure);

    expect($config->logisticsBufferMinutes)->toBeGreaterThan(3);
});

it('suggest minimize duration returns small pool size', function () {
    $config = $this->simulator->suggestOptimalConfig(180, 8, TournamentObjectiveEnum::MinimizeDuration);

    expect($config->poolSize)->toBeLessThanOrEqual(4);
});
