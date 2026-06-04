<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\EventPost;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function adminEventsEvent(array $overrides = []): EventPost
{
    $tournament = Tournament::factory()->create();

    return EventPost::create(array_merge([
        'eventable_type' => Tournament::class,
        'eventable_id' => $tournament->id,
        'type' => ClubEventTypeEnum::TOURNAMENT,
        'title' => 'Événement test',
        'description' => 'Une description.',
        'location' => 'Salle A',
        'status' => EventPostStatusEnum::PUBLISHED->value,
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '09:00:00',
        'icon' => '🏆',
        'featured' => false,
        'featured_until' => null,
    ], $overrides));
}

function eventsAdminIndexAs(User $user)
{
    return Livewire::actingAs($user)->test('pages::website.events.index');
}

// ── Contrôle d'accès ──────────────────────────────────────────────────────────

describe('contrôle d\'accès', function (): void {
    it('est accessible à un admin', function (): void {
        $admin = User::factory()->isAdmin()->create();

        eventsAdminIndexAs($admin)->assertOk();
    });

    it('est accessible à un membre du comité', function (): void {
        $committee = User::factory()->isCommitteeMember()->create();

        eventsAdminIndexAs($committee)->assertOk();
    });
})->group('EventAdmin', 'Authorization');

// ── Filtres ───────────────────────────────────────────────────────────────────

describe('filtres', function (): void {
    it('filtre par statut', function (): void {
        $admin = User::factory()->isAdmin()->create();
        adminEventsEvent(['title' => 'Event Alpha Unique', 'status' => EventPostStatusEnum::DRAFT->value]);
        adminEventsEvent(['title' => 'Event Beta Unique',  'status' => EventPostStatusEnum::PUBLISHED->value]);

        eventsAdminIndexAs($admin)
            ->set('status', EventPostStatusEnum::DRAFT->value)
            ->assertSee('Event Alpha Unique')
            ->assertDontSee('Event Beta Unique');
    });

    it('filtre par type', function (): void {
        $admin = User::factory()->isAdmin()->create();
        adminEventsEvent(['title' => 'Tournoi Alpha Unique', 'type' => ClubEventTypeEnum::TOURNAMENT->value]);
        adminEventsEvent(['title' => 'Stage Alpha Unique',   'type' => ClubEventTypeEnum::TRAINING->value]);

        eventsAdminIndexAs($admin)
            ->set('type', ClubEventTypeEnum::TOURNAMENT->value)
            ->assertSee('Tournoi Alpha Unique')
            ->assertDontSee('Stage Alpha Unique');
    });

    it('filtre par recherche sur le titre', function (): void {
        $admin = User::factory()->isAdmin()->create();
        adminEventsEvent(['title' => 'Grand Tournoi']);
        adminEventsEvent(['title' => 'Stage été']);

        eventsAdminIndexAs($admin)
            ->set('search', 'Tournoi')
            ->assertSee('Grand Tournoi')
            ->assertDontSee('Stage été');
    });

    it('réinitialise les filtres via resetFilters()', function (): void {
        $admin = User::factory()->isAdmin()->create();
        adminEventsEvent(['title' => 'Brouillon', 'status' => EventPostStatusEnum::DRAFT->value]);

        eventsAdminIndexAs($admin)
            ->set('status', EventPostStatusEnum::PUBLISHED->value)
            ->call('resetFilters')
            ->assertSet('status', '')
            ->assertSet('search', '')
            ->assertSet('type', '');
    });
})->group('EventAdmin');

// ── Actions rapides ───────────────────────────────────────────────────────────

describe('actions rapides', function (): void {
    it('publie un événement en brouillon', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent(['status' => EventPostStatusEnum::DRAFT->value]);

        eventsAdminIndexAs($admin)->call('publish', $ep->id);

        expect($ep->fresh()->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });

    it('archive un événement publié', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent(['status' => EventPostStatusEnum::PUBLISHED->value]);

        eventsAdminIndexAs($admin)->call('archive', $ep->id);

        expect($ep->fresh()->status)->toBe(EventPostStatusEnum::ARCHIVED);
    });

    it('supprime un événement en brouillon', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent(['status' => EventPostStatusEnum::DRAFT->value]);

        eventsAdminIndexAs($admin)
            ->call('confirmDelete', $ep->id)
            ->call('delete');

        expect(EventPost::find($ep->id))->toBeNull();
    });

    it('ne supprime pas un événement publié', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent(['status' => EventPostStatusEnum::PUBLISHED->value]);

        eventsAdminIndexAs($admin)
            ->call('confirmDelete', $ep->id)
            ->call('delete');

        expect(EventPost::find($ep->id))->not->toBeNull();
    });
})->group('EventAdmin');

// ── Drawer d'édition ─────────────────────────────────────────────────────────

describe('drawer d\'édition', function (): void {
    it('pré-remplit les champs depuis l\'événement', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent(['title' => 'Titre original', 'location' => 'Salle B', 'featured' => true]);

        eventsAdminIndexAs($admin)
            ->call('openEdit', $ep->id)
            ->assertSet('editDrawer', true)
            ->assertSet('editTitle', 'Titre original')
            ->assertSet('editLocation', 'Salle B')
            ->assertSet('editFeatured', true);
    });

    it('sauvegarde les modifications', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent(['title' => 'Avant']);

        eventsAdminIndexAs($admin)
            ->call('openEdit', $ep->id)
            ->set('editTitle', 'Après')
            ->set('editDescription', 'Nouvelle description.')
            ->set('editLocation', 'Nouvelle salle')
            ->set('editStatus', EventPostStatusEnum::PUBLISHED->value)
            ->set('editEventDate', today()->addWeek()->format('Y-m-d'))
            ->set('editStartTime', '10:00')
            ->call('saveEdit');

        expect($ep->fresh()->title)->toBe('Après');
    });

    it('rejette une date featured_until dans le passé', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent();

        eventsAdminIndexAs($admin)
            ->call('openEdit', $ep->id)
            ->set('editTitle', 'Valide')
            ->set('editDescription', 'Ok.')
            ->set('editLocation', 'Salle')
            ->set('editStatus', EventPostStatusEnum::PUBLISHED->value)
            ->set('editEventDate', today()->addWeek()->format('Y-m-d'))
            ->set('editStartTime', '10:00')
            ->set('editFeatured', true)
            ->set('editFeaturedUntil', today()->subDay()->format('Y-m-d'))
            ->call('saveEdit')
            ->assertHasErrors(['editFeaturedUntil']);
    });

    it('accepte une date featured_until dans le futur', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $ep = adminEventsEvent();

        eventsAdminIndexAs($admin)
            ->call('openEdit', $ep->id)
            ->set('editTitle', 'Valide')
            ->set('editDescription', 'Ok.')
            ->set('editLocation', 'Salle')
            ->set('editStatus', EventPostStatusEnum::PUBLISHED->value)
            ->set('editEventDate', today()->addWeek()->format('Y-m-d'))
            ->set('editStartTime', '10:00')
            ->set('editFeatured', true)
            ->set('editFeaturedUntil', today()->addDays(10)->format('Y-m-d'))
            ->call('saveEdit')
            ->assertHasNoErrors();

        expect($ep->fresh()->featured_until->toDateString())->toBe(today()->addDays(10)->toDateString());
    });
})->group('EventAdmin');

// ── HasEventPostForm — featured_until ────────────────────────────────────────

describe('HasEventPostForm — eventFeaturedUntil', function (): void {
    it('pré-remplit eventFeaturedUntil depuis un EventPost existant', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'start_time' => '10:00:00',
            'duration_minutes' => 180,
            'logistics_buffer_minutes' => 3,
            'sets_to_win' => 3,
            'nb_pools' => 2,
            'pool_size' => 4,
            'nb_qualifiers_per_pool' => 2,
            'match_type' => 'single',
            'has_handicap_points' => false,
            'deuce_enabled' => true,
        ]);
        $futureDate = today()->addDays(30)->format('Y-m-d');

        EventPost::create([
            'eventable_type' => Tournament::class,
            'eventable_id' => $tournament->id,
            'type' => ClubEventTypeEnum::TOURNAMENT,
            'title' => 'Open avec expiry',
            'description' => '',
            'location' => '',
            'status' => EventPostStatusEnum::PUBLISHED->value,
            'event_date' => today()->toDateString(),
            'start_time' => '09:00:00',
            'icon' => '🏆',
            'featured' => true,
            'featured_until' => $futureDate,
        ]);

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->assertSet('eventFeaturedUntil', $futureDate);
    });

    it('sauvegarde featured_until via le wizard', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $tournament = Tournament::factory()->create([
            'status' => TournamentStatusEnum::PUBLISHED,
            'start_time' => '10:00:00',
            'duration_minutes' => 180,
            'logistics_buffer_minutes' => 3,
            'sets_to_win' => 3,
            'nb_pools' => 2,
            'pool_size' => 4,
            'nb_qualifiers_per_pool' => 2,
            'match_type' => 'single',
            'has_handicap_points' => false,
            'deuce_enabled' => true,
        ]);

        $futureDate = today()->addDays(14)->format('Y-m-d');

        Livewire::actingAs($admin)
            ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament])
            ->set('step', '2')
            ->set('eventTitle', 'Open avec expiry')
            ->set('eventDescription', 'Description suffisante pour la publication.')
            ->set('eventLocation', 'Club House')
            ->set('eventFeatured', true)
            ->set('eventFeaturedUntil', $futureDate)
            ->call('saveEventPost', 'published');

        $ep = EventPost::where('eventable_id', $tournament->id)->first();

        expect($ep->featured_until->toDateString())->toBe($futureDate);
    });
})->group('EventAdmin');
