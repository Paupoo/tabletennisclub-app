<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentFinalPhaseService;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Build a tournament with completed pool phase, ready for bracket generation.
 * Returns the tournament with 2 pools of 4, all matches completed.
 */
function tournamentReadyForBracket(int $nbPools = 2, int $poolSize = 4, int $qualifiersPerPool = 2): Tournament
{
    $tournament = Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'sets_to_win' => 3,
        'nb_pools' => $nbPools,
        'nb_qualifiers_per_pool' => $qualifiersPerPool,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
        'price' => 0,
    ]);

    $total = $nbPools * $poolSize;
    $players = User::factory($total)->create();
    $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

    app(TournamentPoolService::class)->distributePlayersInPools($tournament, $nbPools);
    $tournament->load(['pools.users', 'pools.tournament']);
    app(TournamentMatchService::class)->generateTournamentMatches($tournament);

    // Complete every pool match — player1 always wins
    $tournament->matches()->whereNotNull('pool_id')->get()->each(
        fn ($m) => $m->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 4],
        ])
    );

    return $tournament;
}

// ── TournamentFinalPhaseService::configureKnockoutPhase ───────────────────────

describe('configureKnockoutPhase', function () {

    it('throws when pools still have pending matches', function () {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'nb_pools' => 1,
            'nb_qualifiers_per_pool' => 2,
        ]);
        $players = User::factory(4)->create();
        $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        // Do NOT complete the matches — simulate pool still open
        expect(fn () => app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal')
        )->toThrow(Exception::class);
    })->group('bracket', 'guard');

    it('throws when pools have no matches at all', function () {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'nb_pools' => 1,
        ]);
        // Don't generate any matches
        expect(fn () => app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal')
        )->toThrow(DivisionByZeroError::class);
    })->group('bracket', 'guard');

    it('creates a final and a bronze match at minimum', function () {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal');

        $bracketMatches = $tournament->matches()->whereNotNull('round')->get();

        expect($bracketMatches->where('round', 'final'))->toHaveCount(1)
            ->and($bracketMatches->where('round', 'bronze'))->toHaveCount(1);
    })->group('bracket', 'creation');

    it('creates semifinal matches when starting round is semifinal', function () {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal');

        $semifinals = $tournament->matches()->where('round', 'semifinal')->get();
        expect($semifinals)->toHaveCount(2);
    })->group('bracket', 'creation');

    it('creates quarterfinal matches when 8 qualifiers (quarterfinal start)', function () {
        $tournament = tournamentReadyForBracket(nbPools: 4, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'quarterfinal');

        $quarters = $tournament->matches()->where('round', 'quarterfinal')->get();
        expect($quarters)->toHaveCount(4);
    })->group('bracket', 'creation');

    it('produces a non-zero total of bracket matches after generation', function () {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal');

        $bracketCount = $tournament->matches()->whereNotNull('round')->count();
        expect($bracketCount)->toBeGreaterThan(0);
    })->group('bracket', 'creation');

    it('deletes existing bracket matches before re-generating', function () {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);
        $service = app(TournamentFinalPhaseService::class);

        $service->configureKnockoutPhase($tournament, 'semifinal');
        $firstCount = $tournament->matches()->whereNotNull('round')->count();

        $service->configureKnockoutPhase($tournament, 'semifinal');
        $secondCount = $tournament->matches()->whereNotNull('round')->count();

        expect($secondCount)->toBe($firstCount);
    })->group('bracket', 'creation');

})->group('bracket');

// ── TournamentFinalPhaseService::completeMatch ────────────────────────────────

describe('completeMatch', function () {

    it('marks the match as completed with the correct winner', function () {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        app(TournamentFinalPhaseService::class)->completeMatch($match, $p1->id);

        expect($match->fresh()->status)->toBe('completed')
            ->and($match->fresh()->winner_id)->toBe($p1->id);
    })->group('bracket', 'progression');

    it('advances the winner to player1_id of the next match when it is empty', function () {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        $final = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => null,
            'player2_id' => null,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        $semi = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'next_match_id' => $final->id,
            'match_order' => 2,
        ]);

        app(TournamentFinalPhaseService::class)->completeMatch($semi, $p1->id);

        expect($final->fresh()->player1_id)->toBe($p1->id);
    })->group('bracket', 'progression');

    it('advances the winner to player2_id of the next match when player1 is already set', function () {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        [$p1, $p3, $p4] = User::factory(3)->create()->all();

        $final = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => null,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        $semi2 = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p3->id,
            'player2_id' => $p4->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'next_match_id' => $final->id,
            'match_order' => 3,
        ]);

        app(TournamentFinalPhaseService::class)->completeMatch($semi2, $p3->id);

        expect($final->fresh()->player2_id)->toBe($p3->id);
    })->group('bracket', 'progression');

    it('sends the loser of a semifinal to the bronze match', function () {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        $bronze = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => null,
            'player2_id' => null,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'bronze',
            'status' => 'scheduled',
            'is_bronze_match' => true,
            'match_order' => 2,
        ]);

        $final = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => null,
            'player2_id' => null,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        $semi = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'next_match_id' => $final->id,
            'bronze_match_id' => $bronze->id,
            'match_order' => 4,
        ]);

        // p1 wins — p2 (loser) should go to bronze
        app(TournamentFinalPhaseService::class)->completeMatch($semi, $p1->id);

        expect($bronze->fresh()->player1_id)->toBe($p2->id);
    })->group('bracket', 'progression');

})->group('bracket');

// ── generateBracket — startingRound logic ─────────────────────────────────────

describe('generateBracket startingRound selection', function () {

    it('selects semifinal when 4 qualifiers (2 pools × 2)', function () {
        $tournament = Tournament::factory()->create([
            'nb_pools' => 2,
            'nb_qualifiers_per_pool' => 2,
        ]);

        $totalQualifiers = $tournament->nb_pools * $tournament->nb_qualifiers_per_pool;
        $startingRound = match (true) {
            $totalQualifiers >= 9 => 'round_16',
            $totalQualifiers >= 5 => 'quarterfinal',
            default => 'semifinal',
        };

        expect($startingRound)->toBe('semifinal');
    })->group('bracket', 'round-selection');

    it('selects quarterfinal when 8 qualifiers (4 pools × 2)', function () {
        $tournament = Tournament::factory()->create([
            'nb_pools' => 4,
            'nb_qualifiers_per_pool' => 2,
        ]);

        $totalQualifiers = $tournament->nb_pools * $tournament->nb_qualifiers_per_pool;
        $startingRound = match (true) {
            $totalQualifiers >= 9 => 'round_16',
            $totalQualifiers >= 5 => 'quarterfinal',
            default => 'semifinal',
        };

        expect($startingRound)->toBe('quarterfinal');
    })->group('bracket', 'round-selection');

    it('selects round_16 when 16 qualifiers (8 pools × 2)', function () {
        $tournament = Tournament::factory()->create([
            'nb_pools' => 8,
            'nb_qualifiers_per_pool' => 2,
        ]);

        $totalQualifiers = $tournament->nb_pools * $tournament->nb_qualifiers_per_pool;
        $startingRound = match (true) {
            $totalQualifiers >= 9 => 'round_16',
            $totalQualifiers >= 5 => 'quarterfinal',
            default => 'semifinal',
        };

        expect($startingRound)->toBe('round_16');
    })->group('bracket', 'round-selection');

})->group('bracket');
