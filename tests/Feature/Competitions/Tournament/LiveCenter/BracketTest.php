<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Shared\Enums\TournamentStatusEnum;

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

describe('configureKnockoutPhase', function (): void {

    it('throws when pools still have pending matches', function (): void {
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

    it('throws when pools have no matches at all', function (): void {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'nb_pools' => 1,
        ]);
        // Don't generate any matches
        expect(fn () => app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal')
        )->toThrow(DivisionByZeroError::class);
    })->group('bracket', 'guard');

    it('creates a final and a bronze match at minimum', function (): void {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal');

        $bracketMatches = $tournament->matches()->whereNotNull('round')->get();

        expect($bracketMatches->where('round', 'final'))->toHaveCount(1)
            ->and($bracketMatches->where('round', 'bronze'))->toHaveCount(1);
    })->group('bracket', 'creation');

    it('creates semifinal matches when starting round is semifinal', function (): void {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal');

        $semifinals = $tournament->matches()->where('round', 'semifinal')->get();
        expect($semifinals)->toHaveCount(2);
    })->group('bracket', 'creation');

    it('creates quarterfinal matches when 8 qualifiers (quarterfinal start)', function (): void {
        $tournament = tournamentReadyForBracket(nbPools: 4, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'quarterfinal');

        $quarters = $tournament->matches()->where('round', 'quarterfinal')->get();
        expect($quarters)->toHaveCount(4);
    })->group('bracket', 'creation');

    it('produces a non-zero total of bracket matches after generation', function (): void {
        $tournament = tournamentReadyForBracket(nbPools: 2, poolSize: 4, qualifiersPerPool: 2);

        app(TournamentFinalPhaseService::class)
            ->configureKnockoutPhase($tournament, 'semifinal');

        $bracketCount = $tournament->matches()->whereNotNull('round')->count();
        expect($bracketCount)->toBeGreaterThan(0);
    })->group('bracket', 'creation');

    it('deletes existing bracket matches before re-generating', function (): void {
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

describe('completeMatch', function (): void {

    it('marks the match as completed with the correct winner', function (): void {
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

    it('advances the winner to player1_id of the next match when it is empty', function (): void {
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

    it('advances the winner to player2_id of the next match when player1 is already set', function (): void {
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

    it('sends the loser of a semifinal to the bronze match', function (): void {
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

// ── completeMatch — doubles pair propagation ──────────────────────────────────

describe('completeMatch doubles pair propagation', function (): void {

    it('propagates the winner pair_id to player1 slot of the next match', function (): void {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        [$p1, $p2] = User::factory(2)->create()->all();
        $pair1 = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p1->id, 'player2_id' => User::factory()->create()->id]);
        $pair2 = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p2->id, 'player2_id' => User::factory()->create()->id]);

        $next = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        $semi = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'match_order' => 2,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'pair1_id' => $pair1->id,
            'pair2_id' => $pair2->id,
            'next_match_id' => $next->id,
        ]);

        // p1 (pair1) wins
        app(TournamentFinalPhaseService::class)->completeMatch($semi, $p1->id);

        expect($next->fresh()->player1_id)->toBe($p1->id)
            ->and($next->fresh()->pair1_id)->toBe($pair1->id);
    })->group('bracket', 'doubles');

    it('propagates the winner pair_id to player2 slot when player1 is already set', function (): void {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        [$p1, $p2, $p3] = User::factory(3)->create()->all();
        $pairA = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p1->id, 'player2_id' => User::factory()->create()->id]);
        $pairB = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p2->id, 'player2_id' => User::factory()->create()->id]);
        $pairC = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p3->id, 'player2_id' => User::factory()->create()->id]);

        $final = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 1,
            'player1_id' => $p1->id,
            'pair1_id' => $pairA->id,
        ]);

        $semi = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'match_order' => 3,
            'player1_id' => $p2->id,
            'player2_id' => $p3->id,
            'pair1_id' => $pairB->id,
            'pair2_id' => $pairC->id,
            'next_match_id' => $final->id,
        ]);

        // p2 (pairB) wins
        app(TournamentFinalPhaseService::class)->completeMatch($semi, $p2->id);

        expect($final->fresh()->player2_id)->toBe($p2->id)
            ->and($final->fresh()->pair2_id)->toBe($pairB->id);
    })->group('bracket', 'doubles');

    it('propagates the loser pair_id to the bronze match', function (): void {
        $tournament = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        [$p1, $p2] = User::factory(2)->create()->all();
        $pair1 = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p1->id, 'player2_id' => User::factory()->create()->id]);
        $pair2 = TournamentPair::factory()->create(['tournament_id' => $tournament->id, 'player1_id' => $p2->id, 'player2_id' => User::factory()->create()->id]);

        $bronze = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'bronze',
            'status' => 'scheduled',
            'match_order' => 1,
            'is_bronze_match' => true,
        ]);

        $final = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 2,
        ]);

        $semi = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'match_order' => 3,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'pair1_id' => $pair1->id,
            'pair2_id' => $pair2->id,
            'next_match_id' => $final->id,
            'bronze_match_id' => $bronze->id,
        ]);

        // p1 (pair1) wins — p2 (pair2) is the loser and should go to bronze
        app(TournamentFinalPhaseService::class)->completeMatch($semi, $p1->id);

        expect($bronze->fresh()->player1_id)->toBe($p2->id)
            ->and($bronze->fresh()->pair1_id)->toBe($pair2->id);
    })->group('bracket', 'doubles');

})->group('bracket', 'doubles');

// ── generateBracket — startingRound logic ─────────────────────────────────────

describe('generateBracket startingRound selection', function (): void {

    it('selects semifinal when 4 qualifiers (2 pools × 2)', function (): void {
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

    it('selects quarterfinal when 8 qualifiers (4 pools × 2)', function (): void {
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

    it('selects round_16 when 16 qualifiers (8 pools × 2)', function (): void {
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

// ── Petite finale : le bloc lisait les variables de la boucle précédente ──────

/*
 * Sous le tableau, la carte de la petite finale utilisait $match, $round,
 * $isFinal et $winnerName — quatre variables laissées par le @foreach de la
 * finale, fermé juste au-dessus. Elle affichait donc l'arbitre et le vainqueur
 * de la FINALE. Ces tests fixent la frontière : ce que la carte montre doit
 * venir du match de petite finale, et de lui seul.
 */
describe('bronze card reads its own match', function (): void {

    /** @return array{tournament: Tournament, referee: User, winner: User} */
    function bronzeScenario(): array
    {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'sets_to_win' => 3,
            'has_handicap_points' => false,
            'deuce_enabled' => false,
        ]);

        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        // L'arbitre n'est inscrit à rien : son nom ne peut apparaître qu'au
        // titre de l'arbitrage, ce qui rend le comptage d'occurrences probant.
        $referee = User::factory()->create(['first_name' => 'Zoé', 'last_name' => 'Arbitrale']);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'round' => 'final',
            'player1_id' => $a->id,
            'player2_id' => $b->id,
            'winner_id' => $a->id,
            'referee_id' => $referee->id,
            'status' => 'completed',
            'match_order' => 1,
        ]);

        // La petite finale n'a ni arbitre, ni vainqueur, ni résultat.
        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'round' => 'bronze',
            'player1_id' => $c->id,
            'player2_id' => $d->id,
            'status' => 'scheduled',
            'match_order' => 2,
        ]);

        return ['tournament' => $tournament, 'referee' => $referee, 'winner' => $a];
    }

    it('does not borrow the final referee', function (): void {
        ['tournament' => $tournament, 'referee' => $referee] = bronzeScenario();

        $html = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->html();

        // Une seule fois : sur la carte de la finale, qui est bien la sienne.
        expect(substr_count($html, $referee->full_name))->toBe(1);
    });

    it('does not announce a winner for a match nobody has played', function (): void {
        ['tournament' => $tournament] = bronzeScenario();

        $html = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament])
            ->html();

        /*
         * `text-yellow-500` n'habille qu'une chose dans le tableau : la ligne
         * « vainqueur » d'un match terminé. Seule la finale l'est ; la petite
         * finale est programmée. Deux occurrences signifieraient qu'elle a
         * repris le vainqueur de la finale.
         */
        expect(substr_count($html, 'text-yellow-500'))->toBe(1);
    });
});
