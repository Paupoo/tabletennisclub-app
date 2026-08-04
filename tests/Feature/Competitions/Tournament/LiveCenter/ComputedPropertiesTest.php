<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\ClubAdmin\Club\Models\Table;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Pool;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Shared\Enums\TableStateEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

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

function occupyTable(TournamentMatch $match): void
{
    $table = Table::create([
        'name' => 'Table ' . $match->id,
        'state' => TableStateEnum::GOOD,
        'purchased_on' => now()->subYears(2)->toDateString(),
        'room_id' => Room::factory()->create()->id,
    ]);

    $match->tournament->tables()->attach($table->id, [
        'is_table_free' => false,
        'tournament_match_id' => $match->id,
        'match_started_at' => now()->subMinutes(15),
    ]);
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

describe('allMatchesComplete', function (): void {

    it('returns true when there are no scheduled or in_progress matches', function (): void {
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

    it('returns false when a scheduled match exists', function (): void {
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

    it('returns false when an in_progress match exists', function (): void {
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

    it('returns true when tournament has no matches at all', function (): void {
        $tournament = liveCenterTournament();

        $allComplete = ! $tournament->matches()
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->exists();

        expect($allComplete)->toBeTrue();
    })->group('computed', 'closure');

})->group('live-center');

// ── poolsPhaseComplete ────────────────────────────────────────────────────────

describe('poolsPhaseComplete', function (): void {

    it('returns true when every pool has all matches completed', function (): void {
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
            fn (Pool $pool) => $poolService->isPoolFinished($pool)
        );

        expect($allDone)->toBeTrue();
    })->group('computed', 'pools');

    it('returns false when at least one pool has a pending match', function (): void {
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
            fn (Pool $pool) => $poolService->isPoolFinished($pool)
        );

        expect($allDone)->toBeFalse();
    })->group('computed', 'pools');

})->group('live-center');

// ── unpaidParticipants ────────────────────────────────────────────────────────

describe('unpaidParticipants', function (): void {

    it('returns participants with has_paid=false for a paid tournament', function (): void {
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

    it('isPaid() returns false for a free tournament', function (): void {
        $tournament = liveCenterTournament(['price' => 0]);

        expect($tournament->isPaid())->toBeFalse();
    })->group('computed', 'payment');

    it('isPaid() returns true for a paid tournament', function (): void {
        $tournament = liveCenterTournament(['price' => 25]);

        expect($tournament->isPaid())->toBeTrue();
    })->group('computed', 'payment');

    it('excludes cancelled and waitlisted participants from the unpaid list', function (): void {
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

    it('returns all participants as unpaid when none have paid', function (): void {
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

describe('bracketPhaseComplete', function (): void {

    it('returns false when no final match exists', function (): void {
        $tournament = liveCenterTournament();

        $complete = $tournament->matches()
            ->where('round', 'final')
            ->where('status', 'completed')
            ->exists();

        expect($complete)->toBeFalse();
    })->group('computed', 'bracket');

    it('returns false when the final match is not yet completed', function (): void {
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

    it('returns true when the final match is completed', function (): void {
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

describe('TournamentMatchService::calculatePoolStandings', function (): void {

    it('returns correct wins, sets and points after pool matches', function (): void {
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

    it('shows zero stats for players with no completed matches', function (): void {
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

// ── busyPlayerIds ─────────────────────────────────────────────────────────────

describe('busyPlayerIds', function (): void {

    it('returns an empty array when no matches are in progress', function (): void {
        $admin = User::factory()->isAdmin()->create();
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

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);

        expect($component->instance()->busyPlayerIds)->toBeEmpty();
    })->group('computed', 'busy-players');

    it('returns player IDs from in_progress matches', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = liveCenterTournament();
        $p1 = User::factory()->create();
        $p2 = User::factory()->create();
        $pool = $tournament->pools()->create(['name' => 'A']);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);
        occupyTable($match);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);

        expect($component->instance()->busyPlayerIds)
            ->toContain($p1->id)
            ->toContain($p2->id);
    })->group('computed', 'busy-players');

    it('does not include players from scheduled or completed matches', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = liveCenterTournament();
        [$p1, $p2, $p3, $p4] = User::factory(4)->create()->all();
        $pool = $tournament->pools()->create(['name' => 'A']);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);
        occupyTable($match);

        TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p3->id,
            'player2_id' => $p4->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'scheduled',
            'match_order' => 2,
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);

        $busy = $component->instance()->busyPlayerIds;
        expect($busy)->toContain($p1->id)
            ->toContain($p2->id)
            ->not->toContain($p3->id)
            ->not->toContain($p4->id);
    })->group('computed', 'busy-players');

    it('only includes players from the same tournament', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = liveCenterTournament();
        $other = liveCenterTournament();
        [$p1, $p2, $p3, $p4] = User::factory(4)->create()->all();

        $pool = $tournament->pools()->create(['name' => 'A']);
        $otherPool = $other->pools()->create(['name' => 'A']);

        TournamentMatch::create([
            'tournament_id' => $other->id,
            'pool_id' => $otherPool->id,
            'player1_id' => $p3->id,
            'player2_id' => $p4->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);

        $busy = $component->instance()->busyPlayerIds;
        expect($busy)->not->toContain($p3->id)
            ->not->toContain($p4->id);
    })->group('computed', 'busy-players');

    it('includes the referee from an in_progress match', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = liveCenterTournament();
        [$p1, $p2, $ref] = User::factory(3)->create()->all();
        $pool = $tournament->pools()->create(['name' => 'A']);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'referee_id' => $ref->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);
        occupyTable($match);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);

        expect($component->instance()->busyPlayerIds)->toContain($ref->id);
    })->group('computed', 'busy-players');

    it('includes all four pair members from an in_progress doubles match', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PENDING,
            'match_type' => 'double',
            'has_handicap_points' => false,
            'price' => 0,
        ]);
        [$a, $b, $c, $d] = User::factory(4)->create()->all();

        $pair1 = TournamentPair::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $a->id,
            'player2_id' => $b->id,
            'registered_by' => $admin->id,
        ]);
        $pair2 = TournamentPair::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $c->id,
            'player2_id' => $d->id,
            'registered_by' => $admin->id,
        ]);

        $pool = $tournament->pools()->create(['name' => 'A']);

        $match = TournamentMatch::create([
            'tournament_id' => $tournament->id,
            'pool_id' => $pool->id,
            'pair1_id' => $pair1->id,
            'pair2_id' => $pair2->id,
            'player1_id' => $a->id,
            'player2_id' => $c->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'in_progress',
            'match_order' => 1,
        ]);
        occupyTable($match);

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.live-center', ['tournament' => $tournament]);

        $busy = $component->instance()->busyPlayerIds;

        // All four players — including the second members of each pair — must be busy
        expect($busy)->toContain($a->id)
            ->toContain($b->id)
            ->toContain($c->id)
            ->toContain($d->id);
    })->group('computed', 'busy-players', 'doubles');

})->group('live-center');
