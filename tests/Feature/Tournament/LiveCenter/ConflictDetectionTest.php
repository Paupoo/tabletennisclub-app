<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Shared\Enums\TournamentStatusEnum;

function conflictTournament(): Tournament
{
    return Tournament::factory()->create([
        'status' => TournamentStatusEnum::PENDING,
        'match_type' => 'single',
        'has_handicap_points' => false,
    ]);
}

function conflictPool(Tournament $tournament): Pool
{
    return Pool::factory()->for($tournament)->create(['name' => 'A']);
}

function conflictMatch(Tournament $tournament, int $p1, int $p2, ?int $referee = null, string $status = 'scheduled'): TournamentMatch
{
    return TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => conflictPool($tournament)->id,
        'player1_id' => $p1,
        'player2_id' => $p2,
        'referee_id' => $referee,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => $status,
        'match_order' => 1,
    ]);
}

describe('detectStartConflict', function () {
    it('returns null when no active matches exist', function () {
        $tournament = conflictTournament();
        [$a, $b] = User::factory(2)->create()->all();

        $match = conflictMatch($tournament, $a->id, $b->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $match))->toBeNull();
    });

    it('returns null when active players are all different', function () {
        $tournament = conflictTournament();
        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        conflictMatch($tournament, $a->id, $b->id, status: 'in_progress');
        $next = conflictMatch($tournament, $c->id, $d->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->toBeNull();
    });

    it('detects a player already in an active match', function () {
        $tournament = conflictTournament();
        [$a, $b, $c] = User::factory(3)->create()->all();

        conflictMatch($tournament, $a->id, $b->id, status: 'in_progress');
        $next = conflictMatch($tournament, $b->id, $c->id); // $b still playing

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });

    it('detects a referee already playing in an active match', function () {
        $tournament = conflictTournament();
        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        // $a is playing in an active match
        conflictMatch($tournament, $a->id, $b->id, status: 'in_progress');
        // $a is also the referee of the next match
        $next = conflictMatch($tournament, $c->id, $d->id, referee: $a->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });

    it('detects a referee already refereeing another active match', function () {
        $tournament = conflictTournament();
        [$a, $b, $c, $d, $ref] = User::factory(5)->create()->all();

        conflictMatch($tournament, $a->id, $b->id, referee: $ref->id, status: 'in_progress');
        $next = conflictMatch($tournament, $c->id, $d->id, referee: $ref->id);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });

    // Real-world scenario: a doubles pair member is assigned as referee for another match
    // (exactly what happened in tournament 5: user 51 was in pair 8 playing at table 15
    //  AND assigned as referee for the match at table 2)
    it('detects a doubles pair member assigned as referee for another active match', function () {
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'match_type' => 'double',
            'has_handicap_points' => false,
        ]);
        $admin = User::factory()->create();
        [$a, $b, $c, $d, $e, $f, $g, $h] = User::factory(8)->create()->all();

        $pair1 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $a->id, 'player2_id' => $b->id, 'registered_by' => $admin->id]);
        $pair2 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $c->id, 'player2_id' => $d->id, 'registered_by' => $admin->id]);
        $pair3 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $e->id, 'player2_id' => $f->id, 'registered_by' => $admin->id]);
        $pair4 = TournamentPair::create(['tournament_id' => $tournament->id, 'player1_id' => $g->id, 'player2_id' => $h->id, 'registered_by' => $admin->id]);

        $pool = Pool::factory()->for($tournament)->create(['name' => 'A']);

        // Active match: pair1 vs pair2 — $b is playing
        TournamentMatch::create([
            'tournament_id' => $tournament->id, 'pool_id' => $pool->id,
            'pair1_id' => $pair1->id, 'pair2_id' => $pair2->id,
            'player1_id' => $a->id, 'player2_id' => $c->id,
            'player1_handicap_points' => 0, 'player2_handicap_points' => 0,
            'status' => 'in_progress', 'match_order' => 1,
        ]);

        // Next match: pair3 vs pair4, but $b (player2 of pair1) is the referee
        $next = TournamentMatch::create([
            'tournament_id' => $tournament->id, 'pool_id' => $pool->id,
            'pair1_id' => $pair3->id, 'pair2_id' => $pair4->id,
            'player1_id' => $e->id, 'player2_id' => $g->id,
            'referee_id' => $b->id,
            'player1_handicap_points' => 0, 'player2_handicap_points' => 0,
            'status' => 'scheduled', 'match_order' => 2,
        ]);
        $next->load(['pair1', 'pair2']);

        expect(app(TournamentMatchService::class)->detectStartConflict($tournament, $next))->not->toBeNull();
    });
})->group('Tournament', 'Conflict');
