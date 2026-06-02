<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function doublesTournament(): Tournament
{
    return Tournament::factory()->create([
        'match_type' => 'double',
        'doubles_registration_mode' => 'club',
        'status' => 'published',
        'duration_minutes' => 180,
        'logistics_buffer_minutes' => 3,
        'sets_to_win' => 3,
        'deuce_enabled' => true,
        'has_handicap_points' => false,
        'pool_size' => 4,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'max_users' => 16,
    ]);
}

function registeredUsersInTournament(Tournament $tournament, int $count = 2): array
{
    $users = User::factory()->count($count)->create();
    foreach ($users as $user) {
        $tournament->users()->attach($user->id, ['registration_status' => 'registered']);
    }

    return $users->all();
}

// ── TournamentPair model ──────────────────────────────────────────────────────

describe('TournamentPair model', function () {
    it('belongs to a tournament', function () {
        $pair = TournamentPair::factory()->create();
        expect($pair->tournament)->toBeInstanceOf(Tournament::class);
    });

    it('belongs to player1 and player2', function () {
        $pair = TournamentPair::factory()->create();
        expect($pair->player1)->toBeInstanceOf(User::class);
        expect($pair->player2)->toBeInstanceOf(User::class);
    });

    it('returns a display name from last names', function () {
        $p1 = User::factory()->create(['last_name' => 'Dupont']);
        $p2 = User::factory()->create(['last_name' => 'Martin']);
        $tournament = Tournament::factory()->create();
        $admin = User::factory()->create();

        $pair = TournamentPair::factory()->create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'registered_by' => $admin->id,
        ]);

        expect($pair->displayName())->toBe('Dupont/Martin');
    });
});

// ── Tournament relations ──────────────────────────────────────────────────────

describe('Tournament pairs relation', function () {
    it('has a pairs() hasMany relation', function () {
        $tournament = doublesTournament();
        $admin = User::factory()->create();
        [$p1, $p2] = registeredUsersInTournament($tournament);

        TournamentPair::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'registered_by' => $admin->id,
        ]);

        expect($tournament->pairs()->count())->toBe(1);
    });
});

// ── doubles_registration_mode ─────────────────────────────────────────────────

describe('doubles_registration_mode', function () {
    it('is saved on the tournament', function () {
        $t = Tournament::factory()->create(['doubles_registration_mode' => 'self']);
        expect($t->fresh()->doubles_registration_mode)->toBe('self');
    });

    it('defaults to null for single tournaments', function () {
        $t = Tournament::factory()->create(['match_type' => 'single']);
        expect($t->doubles_registration_mode)->toBeNull();
    });
});

// ── Wizard createPair / deletePair ────────────────────────────────────────────

describe('wizard createPair', function () {
    it('creates a pair between two registered players', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $tournament = doublesTournament();
        [$p1, $p2] = registeredUsersInTournament($tournament);

        Livewire::test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('pairPlayer1Id', $p1->id)
            ->set('pairPlayer2Id', $p2->id)
            ->call('createPair')
            ->assertHasNoErrors();

        expect(TournamentPair::where('tournament_id', $tournament->id)->count())->toBe(1);
    });

    it('rejects pairing the same player twice', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $tournament = doublesTournament();
        [$p1] = registeredUsersInTournament($tournament);

        Livewire::test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('pairPlayer1Id', $p1->id)
            ->set('pairPlayer2Id', $p1->id)
            ->call('createPair');

        expect(TournamentPair::where('tournament_id', $tournament->id)->count())->toBe(0);
    });

    it('rejects creating a pair if a player is already paired', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $tournament = doublesTournament();
        [$p1, $p2, $p3] = registeredUsersInTournament($tournament, 3);

        TournamentPair::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'registered_by' => $admin->id,
        ]);

        Livewire::test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('pairPlayer1Id', $p1->id)
            ->set('pairPlayer2Id', $p3->id)
            ->call('createPair');

        expect(TournamentPair::where('tournament_id', $tournament->id)->count())->toBe(1);
    });

    it('deletes a pair', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $tournament = doublesTournament();
        [$p1, $p2] = registeredUsersInTournament($tournament);

        $pair = TournamentPair::create([
            'tournament_id' => $tournament->id,
            'player1_id' => $p1->id,
            'player2_id' => $p2->id,
            'registered_by' => $admin->id,
        ]);

        Livewire::test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('deletePair', $pair->id);

        expect(TournamentPair::where('id', $pair->id)->exists())->toBeFalse();
    });
});
