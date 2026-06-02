<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;

// ── Helpers ───────────────────────────────────────────────────────────────────

function handicapTournament(): Tournament
{
    return Tournament::factory()->create([
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'status' => 'published',
        'has_handicap_points' => true,
        'duration_minutes' => 180,
        'logistics_buffer_minutes' => 3,
        'sets_to_win' => 3,
        'deuce_enabled' => true,
        'pool_size' => 4,
        'nb_pools' => 1,
        'nb_qualifiers_per_pool' => 2,
        'max_users' => 16,
    ]);
}

function makePair(Tournament $t, string $r1, string $r2): TournamentPair
{
    $admin = User::factory()->create();
    $p1 = User::factory()->create(['ranking' => $r1]);
    $p2 = User::factory()->create(['ranking' => $r2]);

    return TournamentPair::create([
        'tournament_id' => $t->id,
        'player1_id' => $p1->id,
        'player2_id' => $p2->id,
        'registered_by' => $admin->id,
    ]);
}

// ── calculateDoublesHandicap ──────────────────────────────────────────────────

describe('calculateDoublesHandicap', function () {
    // Alice(B2)+Bob(C4) vs Clara(B4)+David(D0)
    // pair1 receives: h['B4']['B2']=0, h['D0']['B2']=0, h['B4']['C4']=3, h['D0']['C4']=0 → total=3, ceil(3/4)=1
    // pair2 receives: h['B2']['B4']=1, h['C4']['B4']=0, h['B2']['D0']=4, h['C4']['D0']=2 → total=7, ceil(7/4)=2
    // pair2 is weaker → pair2_handicap=2, pair1_handicap=0
    it('assigns handicap to the weaker pair (pair2)', function () {
        $t = handicapTournament();
        $pair1 = makePair($t, 'B2', 'C4');
        $pair2 = makePair($t, 'B4', 'D0');

        $pair1->load(['player1', 'player2']);
        $pair2->load(['player1', 'player2']);

        $result = app(TournamentMatchService::class)
            ->calculateDoublesHandicap($pair1, $pair2);

        expect($result['pair1_handicap'])->toBe(0)
            ->and($result['pair2_handicap'])->toBe(2);
    });

    it('assigns handicap to pair1 when pair1 is weaker', function () {
        $t = handicapTournament();
        $pair1 = makePair($t, 'B4', 'D0');
        $pair2 = makePair($t, 'B2', 'C4');

        $pair1->load(['player1', 'player2']);
        $pair2->load(['player1', 'player2']);

        $result = app(TournamentMatchService::class)
            ->calculateDoublesHandicap($pair1, $pair2);

        expect($result['pair1_handicap'])->toBe(2)
            ->and($result['pair2_handicap'])->toBe(0);
    });

    it('returns zeros for equally matched pairs', function () {
        $t = handicapTournament();
        $pair1 = makePair($t, 'C0', 'C0');
        $pair2 = makePair($t, 'C0', 'C0');

        $pair1->load(['player1', 'player2']);
        $pair2->load(['player1', 'player2']);

        $result = app(TournamentMatchService::class)
            ->calculateDoublesHandicap($pair1, $pair2);

        expect($result['pair1_handicap'])->toBe(0)
            ->and($result['pair2_handicap'])->toBe(0);
    });

    it('returns zeros when a pair has a missing player', function () {
        $t = handicapTournament();
        $pair1 = makePair($t, 'B2', 'C4');
        $pair2 = TournamentPair::factory()->create(['tournament_id' => $t->id]);

        $pair1->load(['player1', 'player2']);
        $pair2->setRelation('player1', null);
        $pair2->setRelation('player2', null);

        $result = app(TournamentMatchService::class)
            ->calculateDoublesHandicap($pair1, $pair2);

        expect($result['pair1_handicap'])->toBe(0)
            ->and($result['pair2_handicap'])->toBe(0);
    });
});

// ── generateMatches doubles ───────────────────────────────────────────────────

describe('generateMatches for doubles pool', function () {
    it('generates round-robin matches with pair1_id and pair2_id', function () {
        $t = handicapTournament();
        $pool = new Pool(['name' => 'Pool A']);
        $pool->tournament_id = $t->id;
        $pool->save();

        foreach (['B2/C4', 'B4/D0', 'C0/C2'] as $combo) {
            [$r1, $r2] = explode('/', $combo);
            $pair = makePair($t, $r1, $r2);
            $pool->pairs()->attach($pair->id);
        }

        app(TournamentMatchService::class)->generateMatches($pool);

        $matches = TournamentMatch::where('pool_id', $pool->id)->get();
        expect($matches)->toHaveCount(3); // 3 pairs → 3 matches (n*(n-1)/2)
        expect($matches->every(fn ($m) => $m->pair1_id !== null && $m->pair2_id !== null))->toBeTrue();
        expect($matches->every(fn ($m) => $m->player1_id !== null && $m->player2_id !== null))->toBeTrue();
    });

    it('sets proxy player ids matching pair player1s', function () {
        $t = handicapTournament();
        $pool = new Pool(['name' => 'Pool A']);
        $pool->tournament_id = $t->id;
        $pool->save();

        $pair1 = makePair($t, 'B2', 'C4');
        $pair2 = makePair($t, 'B4', 'D0');
        $pool->pairs()->attach([$pair1->id, $pair2->id]);

        app(TournamentMatchService::class)->generateMatches($pool);

        $match = TournamentMatch::where('pool_id', $pool->id)->firstOrFail();
        expect(in_array($match->pair1_id, [$pair1->id, $pair2->id]))->toBeTrue()
            ->and(in_array($match->pair2_id, [$pair1->id, $pair2->id]))->toBeTrue()
            ->and($match->player1_id)->not->toBeNull()
            ->and($match->player2_id)->not->toBeNull();
    });
});

// ── calculatePoolStandings for doubles ────────────────────────────────────────

describe('calculatePoolStandings for doubles', function () {
    it('returns pair display names and counts wins per pair', function () {
        $t = Tournament::factory()->create([
            'match_type' => 'double',
            'doubles_registration_mode' => 'club',
            'has_handicap_points' => false,
            'duration_minutes' => 180,
            'logistics_buffer_minutes' => 3,
            'sets_to_win' => 3,
            'deuce_enabled' => true,
            'pool_size' => 4,
            'nb_pools' => 1,
            'nb_qualifiers_per_pool' => 2,
            'max_users' => 8,
        ]);
        $pool = new Pool(['name' => 'Pool A']);
        $pool->tournament_id = $t->id;
        $pool->save();
        $pair1 = makePair($t, 'B2', 'C4');
        $pair2 = makePair($t, 'B4', 'D0');
        $pool->pairs()->attach([$pair1->id, $pair2->id]);

        // Simulate pair1 winning a completed match
        TournamentMatch::create([
            'tournament_id' => $t->id,
            'pool_id' => $pool->id,
            'pair1_id' => $pair1->id,
            'pair2_id' => $pair2->id,
            'player1_id' => $pair1->player1_id,
            'player2_id' => $pair2->player1_id,
            'winner_id' => $pair1->player1_id,
            'status' => 'completed',
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'match_order' => 1,
        ]);

        $standings = app(TournamentMatchService::class)->calculatePoolStandings($pool);

        expect($standings)->toHaveCount(2);
        expect(isset($standings[0]['pair']))->toBeTrue();
        expect($standings[0]['matches_won'])->toBe(1);
        expect($standings[1]['matches_won'])->toBe(0);
    });
});
