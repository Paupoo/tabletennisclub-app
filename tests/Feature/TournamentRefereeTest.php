<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Models\ClubEvents\Tournament\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentFinalPhaseService;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use Illuminate\Database\Eloquent\Collection;

// ── Helpers ───────────────────────────────────────────────────────────────────

function refereeTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PUBLISHED,
        'pool_size' => 4,
        'nb_pools' => 1,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'match_type' => 'single',
        'has_handicap_points' => false,
    ], $overrides));
}

function refereeUsers(int $count): Collection
{
    return User::factory($count)->create([
        'is_active' => true,
        'is_competitor' => true,
        'ranking' => 'C4',
    ]);
}

// ── assignRefereesToPool ──────────────────────────────────────────────────────

describe('assignRefereesToPool', function () {
    it('assigns a referee to every match in the pool', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools()->with('users')->first();
        app(TournamentMatchService::class)->assignRefereesToPool($pool);

        $matches = TournamentMatch::where('pool_id', $pool->id)->get();
        foreach ($matches as $match) {
            expect($match->referee_id)->not->toBeNull();
        }
    });

    it('never assigns a player as referee for their own match', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools()->with('users')->first();
        app(TournamentMatchService::class)->assignRefereesToPool($pool);

        $matches = TournamentMatch::where('pool_id', $pool->id)->get();
        foreach ($matches as $match) {
            expect($match->referee_id)
                ->not->toBe($match->player1_id)
                ->not->toBe($match->player2_id);
        }
    });

    it('distributes referee duties roughly evenly', function () {
        // 4 players → 6 matches → each player referees ≈ 2 times (max diff = 1)
        $tournament = refereeTournament();
        $users = refereeUsers(4);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools()->with('users')->first();
        app(TournamentMatchService::class)->assignRefereesToPool($pool);

        $counts = TournamentMatch::where('pool_id', $pool->id)
            ->whereNotNull('referee_id')
            ->get()
            ->groupBy('referee_id')
            ->map->count();

        expect($counts->max() - $counts->min())->toBeLessThanOrEqual(1);
    });
});

// ── assignRefereesToPool — doubles ───────────────────────────────────────────

describe('assignRefereesToPool (doubles)', function () {
    it('assigns a referee to every doubles match using pair players as candidates', function () {
        $tournament = refereeTournament(['match_type' => 'double']);
        $admin = User::factory()->create();

        foreach (range(1, 4) as $_) {
            $p1 = User::factory()->create(['ranking' => 'C4']);
            $p2 = User::factory()->create(['ranking' => 'C4']);
            TournamentPair::create([
                'tournament_id' => $tournament->id,
                'player1_id' => $p1->id,
                'player2_id' => $p2->id,
                'registered_by' => $admin->id,
            ]);
        }

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.pairs.player1', 'pools.pairs.player2', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools()->with(['pairs.player1', 'pairs.player2', 'tournament'])->first();
        app(TournamentMatchService::class)->assignRefereesToPool($pool);

        $matches = TournamentMatch::where('pool_id', $pool->id)->get();
        foreach ($matches as $match) {
            expect($match->referee_id)->not->toBeNull();
        }
    });

    it('never assigns a pair member as referee for their own doubles match', function () {
        $tournament = refereeTournament(['match_type' => 'double']);
        $admin = User::factory()->create();

        $pairs = collect();
        foreach (range(1, 4) as $_) {
            $p1 = User::factory()->create(['ranking' => 'C4']);
            $p2 = User::factory()->create(['ranking' => 'C4']);
            $pairs->push(TournamentPair::create([
                'tournament_id' => $tournament->id,
                'player1_id' => $p1->id,
                'player2_id' => $p2->id,
                'registered_by' => $admin->id,
            ]));
        }

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.pairs.player1', 'pools.pairs.player2', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools()->with(['pairs.player1', 'pairs.player2', 'tournament'])->first();
        app(TournamentMatchService::class)->assignRefereesToPool($pool);

        $matches = TournamentMatch::where('pool_id', $pool->id)
            ->with(['pair1', 'pair2'])
            ->get();

        foreach ($matches as $match) {
            $playingIds = array_filter([
                $match->pair1?->player1_id,
                $match->pair1?->player2_id,
                $match->pair2?->player1_id,
                $match->pair2?->player2_id,
            ]);
            expect($match->referee_id)->not->toBeIn($playingIds);
        }
    });
})->group('Tournament', 'Referee', 'Doubles');

// ── assignBracketReferee ──────────────────────────────────────────────────────

describe('assignBracketReferee', function () {
    it('assigns the loser of a bracket match as referee of the next scheduled match', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);

        // match1 is already completed: users[0] won, users[1] lost
        $match1 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'quarterfinal',
            'status' => 'completed',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
            'winner_id' => $users[0]->id,
        ]);

        $match2 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'match_order' => 2,
            'player1_id' => $users[2]->id,
            'player2_id' => $users[3]->id,
            'referee_id' => null,
        ]);

        app(TournamentFinalPhaseService::class)->assignBracketReferee($match1);

        expect($match2->fresh()->referee_id)->toBe($users[1]->id);
    });

    it('does not assign referee if loser is already playing the next match', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);

        // match1 is already completed: users[0] won, users[1] lost
        $match1 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'quarterfinal',
            'status' => 'completed',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
            'winner_id' => $users[0]->id,
        ]);

        // Next match already involves the loser (users[1])
        $match2 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'match_order' => 2,
            'player1_id' => $users[1]->id,
            'player2_id' => $users[2]->id,
            'referee_id' => null,
        ]);

        app(TournamentFinalPhaseService::class)->assignBracketReferee($match1);

        expect($match2->fresh()->referee_id)->toBeNull();
    });

    it('never assigns a referee to the final or bronze match', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);

        $completed = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'completed',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
            'winner_id' => $users[0]->id,
        ]);

        $final = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 2,
            'player1_id' => $users[2]->id,
            'player2_id' => $users[3]->id,
            'referee_id' => null,
        ]);

        $bronze = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'bronze',
            'status' => 'scheduled',
            'match_order' => 3,
            'player1_id' => $users[1]->id,
            'player2_id' => $users[0]->id,
            'referee_id' => null,
        ]);

        app(TournamentFinalPhaseService::class)->assignBracketReferee($completed);

        expect($final->fresh()->referee_id)->toBeNull();
        expect($bronze->fresh()->referee_id)->toBeNull();
    });

    it('completeMatch() triggers bracket referee assignment', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);

        $match1 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'quarterfinal',
            'status' => 'in_progress',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
        ]);

        $match2 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'scheduled',
            'match_order' => 2,
            'player1_id' => $users[2]->id,
            'player2_id' => $users[3]->id,
            'referee_id' => null,
        ]);

        app(TournamentFinalPhaseService::class)->completeMatch($match1, $users[0]->id);

        expect($match2->fresh()->referee_id)->toBe($users[1]->id);
    });
})->group('Tournament', 'Referee');

// ── assignInitialBracketReferees ──────────────────────────────────────────────

/**
 * Build a tournament ready for bracket generation:
 * - $nbPools pools × $poolSize players, all pool matches completed (player1 always wins)
 * - Returns the tournament with pools loaded
 */
function initialRefereeTournament(int $nbPools = 2, int $poolSize = 4): Tournament
{
    $tournament = refereeTournament([
        'nb_pools' => $nbPools,
        'pool_size' => $poolSize,
        'nb_qualifiers_per_pool' => 2,
    ]);
    $players = refereeUsers($nbPools * $poolSize);
    $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

    app(TournamentPoolService::class)->distributePlayersInPools($tournament, $nbPools);
    $tournament->load(['pools.users', 'pools.tournament']);
    app(TournamentMatchService::class)->generateTournamentMatches($tournament);

    $tournament->matches()->whereNotNull('pool_id')->get()->each(
        fn ($m) => $m->recordResult([
            ['player1_score' => 11, 'player2_score' => 5],
            ['player1_score' => 11, 'player2_score' => 7],
            ['player1_score' => 11, 'player2_score' => 4],
        ])
    );

    return $tournament;
}

describe('assignInitialBracketReferees', function () {

    it('assigns pool non-qualifiers as referees to first-round bracket matches', function () {
        $tournament = initialRefereeTournament(nbPools: 2, poolSize: 4);
        $service = app(TournamentFinalPhaseService::class);

        // configureKnockoutPhase calls assignInitialBracketReferees internally
        $service->configureKnockoutPhase($tournament, 'round_4');

        $qualifiedIds = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotNull('player1_id')
            ->get()
            ->flatMap(fn ($m) => [$m->player1_id, $m->player2_id])
            ->filter()->unique()->values()->all();

        $bracketMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotIn('round', ['final', 'bronze'])
            ->whereNotNull('player1_id')
            ->get();

        foreach ($bracketMatches as $m) {
            expect($m->referee_id)->not->toBeNull();
            // referee must be a non-qualifier
            expect($qualifiedIds)->not->toContain($m->referee_id);
        }
    });

    it('distributes assignments evenly — no non-qualifier referees more than once before others referee once', function () {
        // 2 pools × 4 = 8 players, 4 qualify, 4 non-qualifiers → 4 quarterfinal matches
        $tournament = initialRefereeTournament(nbPools: 4, poolSize: 4);
        $service = app(TournamentFinalPhaseService::class);

        $service->configureKnockoutPhase($tournament, 'round_8');

        $bracketMatches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereNotNull('round')
            ->whereNotIn('round', ['final', 'bronze'])
            ->whereNotNull('player1_id')
            ->get();

        $counts = $bracketMatches->groupBy('referee_id')->map->count();
        expect($counts->max() - $counts->min())->toBeLessThanOrEqual(1);
    });

    it('never assigns a referee to final or bronze matches', function () {
        $tournament = initialRefereeTournament(nbPools: 2, poolSize: 4);
        app(TournamentFinalPhaseService::class)->configureKnockoutPhase($tournament, 'round_4');

        $final = TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'final')->first();
        $bronze = TournamentMatch::where('tournament_id', $tournament->id)->where('round', 'bronze')->first();

        expect($final->referee_id)->toBeNull();
        expect($bronze->referee_id)->toBeNull();
    });

})->group('Tournament', 'Referee');
