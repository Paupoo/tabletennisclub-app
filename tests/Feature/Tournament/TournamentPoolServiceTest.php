<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Pool;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentMatch;
use App\Services\TournamentPoolService;
use Illuminate\Support\Facades\Event;

function poolTournament(array $overrides = []): Tournament
{
    $tournament = Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PUBLISHED,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'duration_minutes' => 180,
        'pool_size' => 4,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
    ], $overrides));

    Event::fake();

    return $tournament;
}

// ── distributePlayersInPools ──────────────────────────────────────────────────

describe('distributePlayersInPools', function (): void {
    it('creates the requested number of pools', function (): void {
        $tournament = poolTournament();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        (new TournamentPoolService)->distributePlayersInPools($tournament, 2);

        expect(Pool::where('tournament_id', $tournament->id)->count())->toBe(2);
    });

    it('distributes all registered players across pools', function (): void {
        $tournament = poolTournament();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        (new TournamentPoolService)->distributePlayersInPools($tournament, 2);

        $pooledUserCount = Pool::where('tournament_id', $tournament->id)
            ->with('users')
            ->get()
            ->sum(fn ($pool) => $pool->users->count());

        expect($pooledUserCount)->toBe(4);
    });

    it('distributes players evenly across 2 pools (2 each)', function (): void {
        $tournament = poolTournament();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        (new TournamentPoolService)->distributePlayersInPools($tournament, 2);

        $pools = Pool::where('tournament_id', $tournament->id)->with('users')->get();
        foreach ($pools as $pool) {
            expect($pool->users->count())->toBe(2);
        }
    });

    it('returns empty array when no registered players exist', function (): void {
        $tournament = poolTournament();
        $result = (new TournamentPoolService)->distributePlayersInPools($tournament, 2);
        expect($result)->toBeEmpty();
    });

    it('replaces existing pools before distributing', function (): void {
        $tournament = poolTournament();
        $users = User::factory()->count(4)->create(['ranking' => 'NC']);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        (new TournamentPoolService)->distributePlayersInPools($tournament, 2);
        (new TournamentPoolService)->distributePlayersInPools($tournament, 2);

        expect(Pool::where('tournament_id', $tournament->id)->count())->toBe(2);
    });

    it('ignores cancelled and waiting registrations', function (): void {
        $tournament = poolTournament();
        $registered = User::factory()->count(2)->create(['ranking' => 'NC']);
        $cancelled = User::factory()->create(['ranking' => 'NC']);
        $waiting = User::factory()->create(['ranking' => 'NC']);

        $tournament->users()->attach($registered->pluck('id'), ['registration_status' => 'registered']);
        $tournament->users()->attach($cancelled->id, ['registration_status' => 'cancelled']);
        $tournament->users()->attach($waiting->id, ['registration_status' => 'waiting', 'waitlist_position' => 1]);

        (new TournamentPoolService)->distributePlayersInPools($tournament, 2);

        $pooledCount = Pool::where('tournament_id', $tournament->id)
            ->with('users')->get()
            ->sum(fn ($pool) => $pool->users->count());

        expect($pooledCount)->toBe(2);
    });
});

// ── hasPoolMatches ────────────────────────────────────────────────────────────

describe('hasPoolMatches', function (): void {
    it('returns true when pool has at least one match', function (): void {
        $tournament = poolTournament();

        $pool = new Pool;
        $pool->name = 'Pool A';
        $pool->tournament_id = $tournament->id;
        $pool->save();

        $user1 = User::factory()->create(['ranking' => 'NC']);
        $user2 = User::factory()->create(['ranking' => 'NC']);

        TournamentMatch::create([
            'pool_id' => $pool->id,
            'tournament_id' => $tournament->id,
            'player1_id' => $user1->id,
            'player2_id' => $user2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        expect((new TournamentPoolService)->hasPoolMatches($pool))->toBeTrue();
    });

    it('returns false when pool has no matches', function (): void {
        $tournament = poolTournament();

        $pool = new Pool;
        $pool->name = 'Pool A';
        $pool->tournament_id = $tournament->id;
        $pool->save();

        expect((new TournamentPoolService)->hasPoolMatches($pool))->toBeFalse();
    });
});

// ── isPoolFinished ────────────────────────────────────────────────────────────

describe('isPoolFinished', function (): void {
    it('returns true when all matches are completed', function (): void {
        $tournament = poolTournament();
        $user1 = User::factory()->create(['ranking' => 'NC']);
        $user2 = User::factory()->create(['ranking' => 'NC']);

        $pool = new Pool;
        $pool->name = 'Pool A';
        $pool->tournament_id = $tournament->id;
        $pool->save();

        TournamentMatch::create([
            'pool_id' => $pool->id,
            'tournament_id' => $tournament->id,
            'player1_id' => $user1->id,
            'player2_id' => $user2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'completed',
            'winner_id' => $user1->id,
            'match_order' => 1,
        ]);

        expect((new TournamentPoolService)->isPoolFinished($pool))->toBeTrue();
    });

    it('returns false when at least one match is not completed', function (): void {
        $tournament = poolTournament();
        $user1 = User::factory()->create(['ranking' => 'NC']);
        $user2 = User::factory()->create(['ranking' => 'NC']);

        $pool = new Pool;
        $pool->name = 'Pool A';
        $pool->tournament_id = $tournament->id;
        $pool->save();

        TournamentMatch::create([
            'pool_id' => $pool->id,
            'tournament_id' => $tournament->id,
            'player1_id' => $user1->id,
            'player2_id' => $user2->id,
            'player1_handicap_points' => 0,
            'player2_handicap_points' => 0,
            'status' => 'scheduled',
            'match_order' => 1,
        ]);

        expect((new TournamentPoolService)->isPoolFinished($pool))->toBeFalse();
    });

    it('returns true for a pool with no matches (vacuously finished)', function (): void {
        $tournament = poolTournament();

        $pool = new Pool;
        $pool->name = 'Pool A';
        $pool->tournament_id = $tournament->id;
        $pool->save();

        expect((new TournamentPoolService)->isPoolFinished($pool))->toBeTrue();
    });
});
