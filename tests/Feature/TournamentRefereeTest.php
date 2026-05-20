<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Models\ClubEvents\Tournament\TournamentPair;
use App\Services\TournamentFinalPhaseService;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;
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

        $match1 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'in_progress',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
        ]);

        $match2 = TournamentMatch::factory()->create([
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

        app(TournamentFinalPhaseService::class)->assignBracketReferee($match1, $users[0]->id);

        expect($match2->fresh()->referee_id)->toBe($users[1]->id);
    });

    it('does not assign referee if loser is already playing the next match', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);

        $match1 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'in_progress',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
        ]);

        // Next match already involves the loser (users[1])
        $match2 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 2,
            'player1_id' => $users[1]->id,
            'player2_id' => $users[2]->id,
            'referee_id' => null,
        ]);

        app(TournamentFinalPhaseService::class)->assignBracketReferee($match1, $users[0]->id);

        expect($match2->fresh()->referee_id)->toBeNull();
    });

    it('completeMatch() triggers bracket referee assignment', function () {
        $tournament = refereeTournament();
        $users = refereeUsers(4);

        $match1 = TournamentMatch::factory()->create([
            'tournament_id' => $tournament->id,
            'pool_id' => null,
            'table_id' => null,
            'round' => 'semifinal',
            'status' => 'in_progress',
            'match_order' => 1,
            'player1_id' => $users[0]->id,
            'player2_id' => $users[1]->id,
        ]);

        $match2 = TournamentMatch::factory()->create([
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

        app(TournamentFinalPhaseService::class)->completeMatch($match1, $users[0]->id);

        expect($match2->fresh()->referee_id)->toBe($users[1]->id);
    });
})->group('Tournament', 'Referee');
