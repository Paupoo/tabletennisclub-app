<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentMatchService;
use App\Services\TournamentPoolService;

// ── Helpers ───────────────────────────────────────────────────────────────────

function liveCenterTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PENDING,
        'sets_to_win' => 3,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'has_handicap_points' => false,
        'deuce_enabled' => false,
        'price' => 0,
    ], $overrides));
}

function completedPoolMatch(Tournament $tournament, int $poolId, User $p1, User $p2): TournamentMatch
{
    return TournamentMatch::create([
        'tournament_id' => $tournament->id,
        'pool_id' => $poolId,
        'player1_id' => $p1->id,
        'player2_id' => $p2->id,
        'player1_handicap_points' => 0,
        'player2_handicap_points' => 0,
        'status' => 'completed',
        'winner_id' => $p1->id,
        'match_order' => 1,
    ]);
}

// ── allMatchesComplete ────────────────────────────────────────────────────────

describe('allMatchesComplete', function () {

    it('returns true when there are no scheduled or in_progress matches', function () {
        $tournament = liveCenterTournament();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $pool = $tournament->pools()->create(['name' => 'A']);

        completedPoolMatch($tournament, $pool->id, $p1, $p2);

        $allComplete = ! $tournament->matches()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        expect($allComplete)->toBeTrue();
    })->group('computed', 'closure');

    it('returns false when a scheduled match exists', function () {
        $tournament = liveCenterTournament();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $pool = $tournament->pools()->create(['name' => 'A']);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        $allComplete = ! $tournament->matches()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        expect($allComplete)->toBeFalse();
    })->group('computed', 'closure');

    it('returns false when an in_progress match exists', function () {
        $tournament = liveCenterTournament();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $pool = $tournament->pools()->create(['name' => 'A']);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);

        $allComplete = ! $tournament->matches()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        expect($allComplete)->toBeFalse();
    })->group('computed', 'closure');

    it('returns true when tournament has no matches at all', function () {
        $tournament = liveCenterTournament();

        $allComplete = ! $tournament->matches()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        expect($allComplete)->toBeTrue();
    })->group('computed', 'closure');

})->group('live-center');

// ── poolsPhaseComplete ────────────────────────────────────────────────────────

describe('poolsPhaseComplete', function () {

    it('returns true when every pool has all matches completed', function () {
        $tournament = liveCenterTournament(['nb_pools' => 1]);
        $players = User::factory(3)->create();
        $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $tournament->matches()->update([
            'status' => 'completed',
            'winner_id' => $players->first()->id,
        ]);

        $poolService = app(TournamentPoolService::class);
        $allDone = $tournament->fresh()->pools->every(
            fn ($pool) => $poolService->isPoolFinished($pool)
        );

        expect($allDone)->toBeTrue();
    })->group('computed', 'pools');

    it('returns false when at least one pool has a pending match', function () {
        $tournament = liveCenterTournament(['nb_pools' => 1]);
        $players = User::factory(3)->create();
        $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        // Leave the first match scheduled, complete the rest
        $matches = $tournament->matches()->get();
        $matches->skip(1)->each(fn ($m) => $m->update([
            'status' => 'completed',
            'winner_id' => $m->player1_id,
        ]));

        $poolService = app(TournamentPoolService::class);
        $allDone = $tournament->fresh()->pools->every(
            fn ($pool) => $poolService->isPoolFinished($pool)
        );

        expect($allDone)->toBeFalse();
    })->group('computed', 'pools');

})->group('live-center');

// ── unpaidParticipants ────────────────────────────────────────────────────────

describe('unpaidParticipants', function () {

    it('returns participants with has_paid=false for a paid tournament', function () {
        $tournament = liveCenterTournament(['price' => 25]);
        $paid = User::factory()->create();
        $unpaid = User::factory()->create();

        $tournament->users()->attach($paid->id, [
            'registration_status' => 'confirmed',
            'has_paid' => true,
        ]);
        $tournament->users()->attach($unpaid->id, [
            'registration_status' => 'confirmed',
            'has_paid' => false,
        ]);

        $result = $tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->wherePivot('has_paid', false)
            ->get();

        expect($result)->toHaveCount(1)
            ->and($result->first()->id)->toBe($unpaid->id);
    })->group('computed', 'payment');

    it('isPaid() returns false for a free tournament', function () {
        $tournament = liveCenterTournament(['price' => 0]);

        expect($tournament->isPaid())->toBeFalse();
    })->group('computed', 'payment');

    it('isPaid() returns true for a paid tournament', function () {
        $tournament = liveCenterTournament(['price' => 25]);

        expect($tournament->isPaid())->toBeTrue();
    })->group('computed', 'payment');

    it('excludes cancelled and waitlisted participants from the unpaid list', function () {
        $tournament = liveCenterTournament(['price' => 25]);

        $confirmed = User::factory()->create();
        $cancelled = User::factory()->create();
        $waiting = User::factory()->create();

        $tournament->users()->attach($confirmed->id, [
            'registration_status' => 'confirmed',
            'has_paid' => false,
        ]);
        $tournament->users()->attach($cancelled->id, [
            'registration_status' => 'cancelled',
            'has_paid' => false,
        ]);
        $tournament->users()->attach($waiting->id, [
            'registration_status' => 'waiting',
            'has_paid' => false,
            'waitlist_position' => 1,
        ]);

        $result = $tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->wherePivot('has_paid', false)
            ->get();

        expect($result)->toHaveCount(1)
            ->and($result->first()->id)->toBe($confirmed->id);
    })->group('computed', 'payment');

    it('returns all participants as unpaid when none have paid', function () {
        $tournament = liveCenterTournament(['price' => 25]);
        $users = User::factory(3)->create();
        $users->each(fn ($u) => $tournament->users()->attach($u->id, [
            'registration_status' => 'confirmed',
            'has_paid' => false,
        ]));

        $result = $tournament->users()
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'spot_offered'])
            ->wherePivot('has_paid', false)
            ->get();

        expect($result)->toHaveCount(3);
    })->group('computed', 'payment');

})->group('live-center');

// ── bracketPhaseComplete ──────────────────────────────────────────────────────

describe('bracketPhaseComplete', function () {

    it('returns false when no final match exists', function () {
        $tournament = liveCenterTournament();

        $complete = $tournament->matches()
            ->where('round', 'final')
            ->where('status', 'completed')
            ->exists();

        expect($complete)->toBeFalse();
    })->group('computed', 'bracket');

    it('returns false when the final match is not yet completed', function () {
        $tournament = liveCenterTournament();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'scheduled',
            'match_order' => 99,
        ]);

        $complete = $tournament->matches()
            ->where('round', 'final')
            ->where('status', 'completed')
            ->exists();

        expect($complete)->toBeFalse();
    })->group('computed', 'bracket');

    it('returns true when the final match is completed', function () {
        $tournament = liveCenterTournament();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'round' => 'final',
            'status' => 'completed',
            'winner_id' => $p1->id,
            'match_order' => 99,
        ]);

        $complete = $tournament->matches()
            ->where('round', 'final')
            ->where('status', 'completed')
            ->exists();

        expect($complete)->toBeTrue();
    })->group('computed', 'bracket');

})->group('live-center');

// ── calculatePoolStandings ────────────────────────────────────────────────────

describe('TournamentMatchService::calculatePoolStandings', function () {

    it('returns correct wins, sets and points after pool matches', function () {
        $tournament = liveCenterTournament(['nb_pools' => 1]);
        $players = User::factory(3)->create();
        $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools->first();
        $pool->tournamentmatches()->with('sets')->get()->each(
            fn ($match) => $match->recordResult([
                ['player1_score' => 11, 'player2_score' => 5],
                ['player1_score' => 11, 'player2_score' => 7],
                ['player1_score' => 11, 'player2_score' => 4],
            ])
        );

        $standings = app(TournamentMatchService::class)->calculatePoolStandings($pool);
        $top = $standings->sortByDesc('matches_won')->first();

        expect($top['matches_won'])->toBeGreaterThan(0)
            ->and($top['sets_won'])->toBeGreaterThan(0)
            ->and($top['total_points'])->toBeGreaterThan(0);
    })->group('computed', 'standings');

    it('shows zero stats for players with no completed matches', function () {
        $tournament = liveCenterTournament(['nb_pools' => 1]);
        $players = User::factory(3)->create();
        $tournament->users()->attach($players->pluck('id'), ['registration_status' => 'confirmed']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 1);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $pool = $tournament->pools->first();
        $standings = app(TournamentMatchService::class)->calculatePoolStandings($pool);

        foreach ($standings as $row) {
            expect($row['matches_played'])->toBe(0)
                ->and($row['matches_won'])->toBe(0)
                ->and($row['sets_won'])->toBe(0);
        }
    })->group('computed', 'standings');

})->group('live-center');
