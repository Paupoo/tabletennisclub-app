<?php

declare(strict_types=1);

use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Notifications\TournamentInvitationNotification;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function wizardTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status' => TournamentStatusEnum::PUBLISHED,
        'duration_minutes' => 180,
        'pool_size' => 4,
        'nb_pools' => 2,
        'nb_qualifiers_per_pool' => 2,
        'sets_to_win' => 3,
        'logistics_buffer_minutes' => 3,
        'match_type' => 'single',
        'has_handicap_points' => false,
        'deuce_enabled' => false,
    ], $overrides));
}

function competitiveUsers(int $count): Collection
{
    return User::factory($count)->create([
        'is_active' => true,
        'is_competitor' => true,
    ]);
}

// ── sendInvitations ───────────────────────────────────────────────────────────

describe('sendInvitations', function () {
    it('dispatches invitation notification to each selected user', function () {
        Notification::fake();

        $tournament = wizardTournament();
        $users = competitiveUsers(3);

        $notification = new TournamentInvitationNotification(
            tournament: $tournament,
            customMessage: 'Bring your best game!',
        );

        foreach ($users as $user) {
            $user->notify($notification);
        }

        Notification::assertSentTo($users[0], TournamentInvitationNotification::class);
        Notification::assertSentTo($users[2], TournamentInvitationNotification::class);
        Notification::assertCount(3);
    });

    it('creates a tournament_invitations record', function () {
        $tournament = wizardTournament();
        $users = competitiveUsers(4);

        DB::table('tournament_invitations')->insert([
            'tournament_id' => $tournament->id,
            'user_count' => $users->count(),
            'message' => 'See you there!',
            'include_article' => false,
            'sent_at' => now(),
        ]);

        expect(
            DB::table('tournament_invitations')
                ->where('tournament_id', $tournament->id)
                ->where('user_count', 4)
                ->exists()
        )->toBeTrue();
    });
});

// ── confirmBulkPresence ───────────────────────────────────────────────────────

describe('confirmBulkPresence', function () {
    it('updates registration_status to confirmed for selected users', function () {
        $tournament = wizardTournament();
        $users = competitiveUsers(3);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        DB::table('tournament_user')
            ->where('tournament_id', $tournament->id)
            ->whereIn('user_id', $users->pluck('id'))
            ->update(['registration_status' => 'confirmed']);

        foreach ($users as $user) {
            expect(
                DB::table('tournament_user')
                    ->where('tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->value('registration_status')
            )->toBe('confirmed');
        }
    });

    it('updates registration_status to no_show', function () {
        $tournament = wizardTournament();
        $user = competitiveUsers(1)->first();
        $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

        DB::table('tournament_user')
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->update(['registration_status' => 'no_show']);

        expect(
            DB::table('tournament_user')
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('no_show');
    });
});

// ── generatePools ─────────────────────────────────────────────────────────────

describe('generatePools', function () {
    it('creates the correct number of pools', function () {
        $tournament = wizardTournament(['nb_pools' => 3]);
        $users = competitiveUsers(9);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 3);

        expect($tournament->pools()->count())->toBe(3);
    });

    it('distributes all registered players across pools', function () {
        $tournament = wizardTournament(['nb_pools' => 2]);
        $users = competitiveUsers(8);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

        $totalPlayersInPools = $tournament->pools()
            ->with('users')
            ->get()
            ->sum(fn ($pool) => $pool->users->count());

        expect($totalPlayersInPools)->toBe(8);
    });

    it('distributes players evenly between pools (serpentine)', function () {
        $tournament = wizardTournament(['nb_pools' => 2]);
        $users = competitiveUsers(8);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

        $pools = $tournament->pools()->with('users')->get();

        foreach ($pools as $pool) {
            expect($pool->users)->toHaveCount(4);
        }
    });
});

// ── generateMatches ───────────────────────────────────────────────────────────

describe('generateMatches', function () {
    it('creates round-robin matches for all pools', function () {
        $tournament = wizardTournament(['nb_pools' => 2]);
        $users = competitiveUsers(8);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        // Each pool of 4 players: 4*(4-1)/2 = 6 matches, 2 pools = 12 total
        expect($tournament->matches()->count())->toBe(12);
    });
});

// ── processLaunch ─────────────────────────────────────────────────────────────

describe('processLaunch', function () {
    it('transitions tournament from SETUP to PENDING', function () {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);
        $users = competitiveUsers(8);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PENDING);
    });

    it('cannot launch without pools', function () {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);

        expect($tournament->pools()->exists())->toBeFalse();
        expect($tournament->matches()->exists())->toBeFalse();
    });
})->group('Tournament', 'Wizard');

// ── generatePools for doubles ─────────────────────────────────────────────────

describe('generatePools for doubles', function () {
    it('distributes pairs across pools', function () {
        $tournament = wizardTournament(['match_type' => 'double', 'nb_pools' => 2]);
        $admin = User::factory()->create();

        foreach (range(1, 4) as $_) {
            $p1 = User::factory()->create(['ranking' => 'B2']);
            $p2 = User::factory()->create(['ranking' => 'C4']);
            TournamentPair::create([
                'tournament_id' => $tournament->id,
                'player1_id' => $p1->id,
                'player2_id' => $p2->id,
                'registered_by' => $admin->id,
            ]);
        }

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

        expect($tournament->pools()->count())->toBe(2);

        $totalPairsInPools = $tournament->pools()
            ->with('pairs')
            ->get()
            ->sum(fn ($pool) => $pool->pairs->count());

        expect($totalPairsInPools)->toBe(4);
    });

    it('returns empty and creates no pools when no pairs exist', function () {
        $tournament = wizardTournament(['match_type' => 'double', 'nb_pools' => 2]);

        $result = app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

        expect($result)->toBeEmpty();
        expect($tournament->pools()->count())->toBe(0);
    });
})->group('Tournament', 'Wizard', 'Doubles');

// ── saveEventPost validation ──────────────────────────────────────────────────

describe('saveEventPost', function () {

    it('rejects publish when title is missing and shows an error toast', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = wizardTournament();

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('eventTitle', '')
            ->set('eventDescription', 'A proper description for the event.')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'published');

        $component->assertHasErrors(['eventTitle']);
    });

    it('rejects publish when description is missing and shows an error toast', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = wizardTournament();

        $component = Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('eventTitle', 'Spring Open 2026')
            ->set('eventDescription', '')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'published');

        $component->assertHasErrors(['eventDescription']);
    });

    it('publishes without location — location is managed in setup step', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = wizardTournament();

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('eventTitle', 'Spring Open 2026')
            ->set('eventDescription', 'A proper description for the event.')
            ->set('eventLocation', '')
            ->call('saveEventPost', 'published')
            ->assertHasNoErrors(['eventLocation']);
    });

    it('saves as draft with only a title — description and location are optional', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = wizardTournament();

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('eventTitle', 'Spring Open 2026')
            ->set('eventDescription', '')
            ->set('eventLocation', '')
            ->call('saveEventPost', 'draft')
            ->assertHasNoErrors();
    });

    it('publishes successfully when all required fields are filled', function () {
        $admin = User::factory()->isAdmin()->create();
        $tournament = wizardTournament();

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('eventTitle', 'Spring Open 2026')
            ->set('eventDescription', 'A proper description for the event.')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'published')
            ->assertHasNoErrors();

        expect(EventPost::where('eventable_id', $tournament->id)->first())
            ->not->toBeNull()
            ->and(EventPost::where('eventable_id', $tournament->id)->first()->status->value)
            ->toBe('PUBLISHED');
    });

})->group('Tournament', 'Wizard', 'Article');
