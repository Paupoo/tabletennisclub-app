<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentMatch;
use App\Domains\Competitions\Tournament\Models\TournamentPair;
use App\Domains\Competitions\Tournament\Services\TournamentMatchService;
use App\Domains\Competitions\Tournament\Services\TournamentPoolService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use App\Jobs\SendTournamentInvitationJob;
use Illuminate\Bus\PendingBatch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;
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
    return User::factory($count)->create();
}

/** A match already played, which freezes the tournament against being walked back. */
function playedMatch(Tournament $tournament): TournamentMatch
{
    return TournamentMatch::factory()->create([
        'tournament_id' => $tournament->id,
        'pool_id' => null,
        'table_id' => null,
        'status' => 'completed',
    ]);
}

// ── sendInvitations ───────────────────────────────────────────────────────────

describe('sendInvitations', function (): void {
    /*
     * This used to notify the three members by hand and then assert they had
     * been notified, which is a test of nothing: it passed whatever the wizard
     * did. It now calls the wizard, and what it asserts is that the mailing is
     * queued rather than sent — one job per member, none of them leaving in the
     * request.
     */
    it('queues one throttled job per selected member instead of sending in the request', function (): void {
        Bus::fake();
        Notification::fake();

        $tournament = wizardTournament(['registration_deadline' => now()->addWeek()]);
        $users = competitiveUsers(3);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('inviteMessage', 'Bring your best game!')
            ->set('selectedMembers', $users->pluck('id')->all())
            ->call('sendInvitations');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 3
            && $batch->jobs->every(fn (object $job): bool => $job instanceof SendTournamentInvitationJob)
            && $batch->jobs->every(fn (SendTournamentInvitationJob $job): bool => $job->customMessage === 'Bring your best game!'));

        Notification::assertNothingSent();
    });

    it('creates a tournament_invitations record', function (): void {
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

describe('confirmBulkPresence', function (): void {
    it('updates registration_status to confirmed for selected users', function (): void {
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

    it('updates registration_status to no_show', function (): void {
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

describe('generatePools', function (): void {
    it('creates the correct number of pools', function (): void {
        $tournament = wizardTournament(['nb_pools' => 3]);
        $users = competitiveUsers(9);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 3);

        expect($tournament->pools()->count())->toBe(3);
    });

    it('distributes all registered players across pools', function (): void {
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

    it('distributes players evenly between pools (serpentine)', function (): void {
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

describe('generateMatches', function (): void {
    it('creates round-robin matches for all pools', function (): void {
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

describe('processLaunch', function (): void {
    it('transitions tournament from SETUP to PENDING', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);
        $users = competitiveUsers(8);
        $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

        app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);
        $tournament->load(['pools.users', 'pools.tournament']);
        app(TournamentMatchService::class)->generateTournamentMatches($tournament);

        $tournament->update(['status' => TournamentStatusEnum::PENDING]);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PENDING);
    });

    it('cannot launch without pools', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);

        expect($tournament->pools()->exists())->toBeFalse();
        expect($tournament->matches()->exists())->toBeFalse();
    });
})->group('Tournament', 'Wizard');

// ── generatePools for doubles ─────────────────────────────────────────────────

describe('generatePools for doubles', function (): void {
    it('distributes pairs across pools', function (): void {
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

    it('returns empty and creates no pools when no pairs exist', function (): void {
        $tournament = wizardTournament(['match_type' => 'double', 'nb_pools' => 2]);

        $result = app(TournamentPoolService::class)->distributePlayersInPools($tournament, 2);

        expect($result)->toBeEmpty();
        expect($tournament->pools()->count())->toBe(0);
    });
})->group('Tournament', 'Wizard', 'Doubles');

// ── saveEventPost validation ──────────────────────────────────────────────────

describe('saveEventPost', function (): void {

    it('rejects publish when title is missing and shows an error toast', function (): void {
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

    it('rejects publish when description is missing and shows an error toast', function (): void {
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

    it('publishes without location — location is managed in setup step', function (): void {
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

    it('saves as draft with only a title — description and location are optional', function (): void {
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

    it('publishes successfully when all required fields are filled', function (): void {
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

// ── registerableMembersOptions (regression: is_active column was dropped) ────────

describe('registerableMembersOptions', function (): void {
    it('lists active members and excludes users without an active subscription', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $season = makeActiveSeason();

        $active = activeMember($season, [
            'first_name' => 'Olga',
            'last_name' => 'Activsky',
        ]);

        // No subscription at all — not active for the current season.
        User::factory()->create([
            'first_name' => 'Igor',
            'last_name' => 'Inactif',
        ]);

        $tournament = wizardTournament();

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '5')
            // La liste des membres inscriptibles vit dans la modale d'inscription.
            ->set('showRegisterModal', true)
            ->assertSee('Olga Activsky')
            ->assertDontSee('Igor Inactif');

        expect($active->is_active)->toBeTrue();
    });
})->group('Tournament', 'Wizard');

// ── Registration ceiling (issue #37) ─────────────────────────────────────────

/*
 * The wizard read `maxUsers` in ten places — "Inscrits 12 / 24", "Places
 * restantes", the guard that closes registrations — and offered nowhere to set
 * it. The only writer was a private helper deriving it from the structure, so
 * the ceiling the committee saw was one nobody had chosen, and the structure is
 * itself suggested from the tables the selected rooms hold.
 */
describe('registration ceiling', function (): void {
    function wizardAdmin(): User
    {
        return User::factory()->isAdmin()->create();
    }

    /*
     * The whole ticket is that the field did not exist: asserting the behaviour
     * without asserting the input proves nothing a committee member can reach.
     */
    it('offers the committee a field to set it', function (): void {
        $tournament = wizardTournament();

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            // A published tournament opens on step 4; the ceiling lives on step 1.
            ->set('step', '1')
            ->assertSeeHtml('wire:model.live.debounce.500ms="maxUsers"');
    });

    it('follows the structure until somebody sets it', function (): void {
        $tournament = wizardTournament(['nb_pools' => 2, 'pool_size' => 4, 'max_users' => 8]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('maxUsersManual', false)
            ->set('nb_poules', 5)
            ->assertSet('maxUsers', 20)
            ->set('pool_size', 6)
            ->assertSet('maxUsers', 30);
    });

    /*
     * The old guard compared maxUsers to the *new* capacity while its comment
     * claimed the old one, so a second change to the structure never moved the
     * ceiling again. This is that second change.
     */
    it('keeps following the structure past the first change', function (): void {
        $tournament = wizardTournament(['nb_pools' => 2, 'pool_size' => 4, 'max_users' => 8]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('nb_poules', 3)
            ->set('nb_poules', 7)
            ->assertSet('maxUsers', 28);
    });

    it('stops following it once the committee types a number', function (): void {
        $tournament = wizardTournament(['nb_pools' => 2, 'pool_size' => 4, 'max_users' => 8]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('maxUsers', 12)
            ->assertSet('maxUsersManual', true)
            ->set('nb_poules', 9)
            ->assertSet('maxUsers', 12);
    });

    it('can be handed back to the structure', function (): void {
        $tournament = wizardTournament(['nb_pools' => 3, 'pool_size' => 4, 'max_users' => 12]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('maxUsers', 12345)
            ->call('resetMaxUsersToStructure')
            ->assertSet('maxUsersManual', false)
            ->assertSet('maxUsers', 12);
    });

    /*
     * A ceiling already stored that does not match the structure was typed by
     * somebody: reopening the wizard must not quietly overwrite it.
     */
    it('treats a stored ceiling that differs from the structure as deliberate', function (): void {
        $tournament = wizardTournament(['nb_pools' => 4, 'pool_size' => 4, 'max_users' => 12]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('maxUsersManual', true)
            ->set('nb_poules', 8)
            ->assertSet('maxUsers', 12);
    });
});

// ── Opening registrations (issue #35) ────────────────────────────────────────

/*
 * The committee published a tournament and watched the status stay on "draft".
 * There was no button to press: the hop from locked to published was a side
 * effect of sending the first invitation, and nothing said so. What they had
 * published was the article, which is a separate axis entirely.
 *
 * Opening the registrations is now a named action, and it is a prerequisite for
 * inviting anybody — an invitation to a tournament nobody can sign up for leads
 * the member nowhere.
 */
describe('opening registrations', function (): void {
    it('offers the action while the tournament is only locked', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::LOCKED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('step', '4')
            ->assertSee(__('Open registrations'))
            ->assertSee(__('Registrations are closed'));
    });

    it('says so instead once they are open', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('step', '4')
            ->assertSee(__('Registrations are open — members can sign up.'))
            ->assertDontSee(__('Members cannot see this tournament yet, and invitations would lead them nowhere.'));
    });

    it('moves a locked tournament to published', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::LOCKED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('confirmOpenRegistrations')
            ->assertSet('showOpenRegistrationsModal', false);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });

    /* The same button reopens a tournament whose registrations were closed. */
    it('reopens a tournament that was closed for setup', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('confirmOpenRegistrations');

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });

    /* Name and price are not locked yet in draft: opening would skip the contract. */
    it('refuses to open a tournament still in draft', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::DRAFT]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('confirmOpenRegistrations');

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::DRAFT);
    });
});

describe('inviting members', function (): void {
    it('refuses to invite anybody while the registrations are closed', function (): void {
        Bus::fake();
        Notification::fake();

        $tournament = wizardTournament([
            'status' => TournamentStatusEnum::LOCKED,
            'registration_deadline' => now()->addWeek(),
        ]);
        $member = User::factory()->create();

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('selectedMembers', [$member->id])
            ->call('sendInvitations');

        Bus::assertNothingBatched();
        Notification::assertNothingSentTo($member);
        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::LOCKED);
    });

    /*
     * The hop that used to hide here is gone: sending invitations is now only
     * sending invitations.
     */
    it('leaves the status alone once they are open', function (): void {
        Bus::fake();
        Notification::fake();

        $tournament = wizardTournament([
            'status' => TournamentStatusEnum::PUBLISHED,
            'registration_deadline' => now()->addWeek(),
        ]);
        $member = User::factory()->create();

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('selectedMembers', [$member->id])
            ->call('sendInvitations');

        Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->jobs->count() === 1);
        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });
});

// ── Transitions the state machine refuses, and what the committee is told ─────

describe('closing registrations', function (): void {
    it('closes them once somebody has entered', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);
        $tournament->users()->attach(competitiveUsers(1));

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('confirmCloseRegistrations')
            ->assertSet('showCloseRegistrationsModal', false);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::SETUP);
    });

    /*
     * There is nothing to close on a tournament nobody joined, and the wizard
     * has to say which way out exists rather than fail silently.
     */
    it('refuses on a tournament nobody joined, and says to cancel it instead', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('confirmCloseRegistrations')
            ->assertSet('showCloseRegistrationsModal', false);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PUBLISHED);
    });

    /* A refusal must not strand the waitlist: they were not kicked. */
    it('leaves the waitlist alone when it refuses', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('confirmCloseRegistrations');

        expect(DB::table('tournament_user')->where('tournament_id', $tournament->id)->count())->toBe(0);
    });
});

describe('cancelling a tournament', function (): void {
    it('cancels one that has not been played', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('cancelTournament')
            ->assertSet('showCancelModal', false);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::CANCELLED);
    });

    /*
     * Cancelling a tournament whose matches have been played would strand
     * results that were announced in the room.
     */
    it('refuses once a match has been played', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PENDING]);
        playedMatch($tournament);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('cancelTournament')
            ->assertSet('showCancelModal', false);

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::PENDING);
    });

    it('refuses on a tournament that is already closed', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::CLOSED]);

        Livewire::actingAs(wizardAdmin())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->call('cancelTournament');

        expect($tournament->fresh()->status)->toBe(TournamentStatusEnum::CLOSED);
    });
});

// ── Informations complémentaires ─────────────────────────────────────────────

/*
 * Le champ existait dans le formulaire depuis le début, sans wire:model ni
 * propriété : ce que le comité y tapait disparaissait au premier aller-retour
 * Livewire, sans le moindre signal. La colonne `description`, elle, existait
 * déjà sur la table — elle manquait seulement au $fillable.
 */
describe('additional information', function (): void {

    it('stores what the committee types', function (): void {
        $tournament = wizardTournament();

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '1')
            ->set('description', 'Tenue de club obligatoire. Buvette ouverte dès 8h30.')
            ->call('save');

        expect($tournament->fresh()->description)
            ->toBe('Tenue de club obligatoire. Buvette ouverte dès 8h30.');
    });

    it('reads it back when the wizard reopens', function (): void {
        $tournament = wizardTournament(['description' => 'Raquettes fournies sur demande.']);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('description', 'Raquettes fournies sur demande.');
    });

    it('binds the field so the value can reach the component', function (): void {
        $tournament = wizardTournament();

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '1')
            ->assertSeeHtml('wire:model="description"');
    });
});

// ── L'étape vit dans l'URL ───────────────────────────────────────────────────

/*
 * mount() déduisait l'étape du statut, sans exception : l'icône réglages de la
 * liste ouvrait donc les invitations pour un tournoi publié, et jamais la
 * configuration. Une étape explicite doit l'emporter.
 */
describe('wizard step', function (): void {

    it('still derives the step from the status when the URL says nothing', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('step', '4');
    });

    it('lets an explicit step win over the one derived from the status', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->withQueryParams(['step' => '3'])
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('step', '3');
    });

    it('opens the configuration when the settings icon asks for it', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::PUBLISHED]);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->withQueryParams(['step' => '1'])
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('step', '1')
            ->assertSee(__('Details'));
    });
});

// ── Le simulateur reste sous les yeux ────────────────────────────────────────

/*
 * Le verdict de faisabilité vivait ~1780 px sous les champs qui le déterminent
 * -- 2,7 écrans sur un portable 1366x768. Il occupe maintenant une colonne
 * collée, et une barre compacte prend le relais quand cette colonne n'a plus
 * la place.
 */
describe('feasibility panel placement', function (): void {

    it('follows the scroll beside the form', function (): void {
        $tournament = wizardTournament();

        $html = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '1')
            ->html();

        expect($html)->toContain('xl:sticky');

        // Le verdict et le bouton d'enregistrement partagent la colonne collée :
        // dans une page de cette longueur, le bouton était lui aussi hors d'atteinte.
        $sticky = strpos($html, 'xl:sticky');
        $panel = strpos($html, (string) __('Feasibility simulation'));
        $save = strpos($html, (string) __('Update tournament'));

        expect($panel)->toBeGreaterThan($sticky)->and($save)->toBeGreaterThan($panel);
    });

    it('falls back to a compact bar when the column has no room', function (): void {
        $tournament = wizardTournament();

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '1')
            ->assertSeeHtml('sticky top-2 z-10 xl:hidden');
    });

    it('offers the save button once, not twice', function (): void {
        $tournament = wizardTournament();

        $html = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '1')
            ->html();

        expect(substr_count($html, (string) __('Update tournament')))->toBe(1);
    });
});

// ── Le fil des six étapes sur téléphone ──────────────────────────────────────

/*
 * La ligne métro mesure ~825 px pour 335 disponibles sur un téléphone, sans
 * signaler son débordement ni amener l'étape active dans le champ de vision --
 * alors que mount() peut en choisir la cinquième.
 */
describe('mobile step selector', function (): void {

    it('says where you are and how many steps there are', function (): void {
        $tournament = wizardTournament(['status' => TournamentStatusEnum::SETUP]);

        Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSee(__('Step :current of :total', ['current' => 5, 'total' => 6]));
    });

    it('keeps the metro line for wider screens', function (): void {
        $tournament = wizardTournament();

        $html = Livewire::actingAs(User::factory()->isAdmin()->create())
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->html();

        // Le sélecteur sous md, la ligne métro à partir de md : jamais les deux.
        expect($html)->toContain('mb-8 md:hidden')
            ->and($html)->toContain('mb-8 hidden items-center gap-0 overflow-x-auto md:flex');
    });
});
