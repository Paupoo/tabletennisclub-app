<?php

declare(strict_types=1);

use App\Enums\ClubEventTypeEnum;
use App\Enums\EventPostStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubPosts\EventPost;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function eventPostTournament(array $overrides = []): Tournament
{
    return Tournament::factory()->create(array_merge([
        'status'                    => TournamentStatusEnum::PUBLISHED,
        'price'                     => 10,
        'max_users'                 => 16,
        'duration_minutes'          => 180,
        'logistics_buffer_minutes'  => 3,
        'sets_to_win'               => 3,
        'nb_pools'                  => 2,
        'pool_size'                 => 4,
        'nb_qualifiers_per_pool'    => 2,
        'match_type'                => 'single',
        'has_handicap_points'       => false,
        'deuce_enabled'             => true,
        'start_time'                => '10:00:00',
        'location'                  => 'Club House, Rue des Sports 1',
    ], $overrides));
}

function mountWizard(User $admin, Tournament $tournament)
{
    return Livewire::actingAs($admin)
        ->test('pages::club-events.tournaments.wizard', ['tournament' => $tournament]);
}

// ── saveEventPost — création ──────────────────────────────────────────────────

describe('saveEventPost — create', function () {
    it('creates an EventPost in draft status', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'Spring Open 2026')
            ->set('eventDescription', 'Annual club tournament open to all members.')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $tournament->id)->first();

        expect($ep)->not->toBeNull()
            ->and($ep->title)->toBe('Spring Open 2026')
            ->and($ep->status)->toBe(EventPostStatusEnum::DRAFT)
            ->and($ep->type)->toBe(ClubEventTypeEnum::TOURNAMENT)
            ->and($ep->location)->toBe('Club House');
    });

    it('creates an EventPost in published status', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'Spring Open 2026')
            ->set('eventDescription', 'Open to all.')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'published');

        $ep = EventPost::where('eventable_id', $tournament->id)->first();

        expect($ep->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });

    it('syncs start_time and computes end_time from duration_minutes', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament(['start_time' => '09:00:00', 'duration_minutes' => 240]);

        mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'Morning Open')
            ->set('eventLocation', 'Salle A')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $tournament->id)->first();

        expect($ep->start_time->format('H:i'))->toBe('09:00')
            ->and($ep->end_time->format('H:i'))->toBe('13:00');
    });

    it('stores null end_time when tournament has no start_time', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament(['start_time' => null]);

        mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'No-time Tournament')
            ->set('eventLocation', '')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $tournament->id)->first();

        expect($ep->end_time)->toBeNull();
    });

    it('sets the polymorphic relation to the tournament', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'Open 2026')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'draft');

        $ep = EventPost::where('eventable_id', $tournament->id)->first();

        expect($ep->eventable_type)->toBe(Tournament::class)
            ->and($ep->eventable_id)->toEqual($tournament->id);
    });

    it('fails validation when title is missing', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', '')
            ->call('saveEventPost', 'draft')
            ->assertHasErrors(['eventTitle']);

        expect(EventPost::where('eventable_id', $tournament->id)->exists())->toBeFalse();
    });
});

// ── saveEventPost — mise à jour ───────────────────────────────────────────────

describe('saveEventPost — update', function () {
    it('updates an existing EventPost instead of creating a new one', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        $component = mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'Initial Title')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'draft');

        expect(EventPost::count())->toBe(1);

        $component
            ->set('eventTitle', 'Updated Title')
            ->call('saveEventPost', 'published');

        expect(EventPost::count())->toBe(1)
            ->and(EventPost::first()->title)->toBe('Updated Title')
            ->and(EventPost::first()->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });

    it('updates status from draft to published', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        $component = mountWizard($admin, $tournament)
            ->set('step', '2')
            ->set('eventTitle', 'Spring Open')
            ->set('eventLocation', 'Club House')
            ->call('saveEventPost', 'draft');

        expect(EventPost::first()->status)->toBe(EventPostStatusEnum::DRAFT);

        $component->call('saveEventPost', 'published');

        expect(EventPost::first()->status)->toBe(EventPostStatusEnum::PUBLISHED);
    });
});

// ── Chargement depuis l'EventPost existant ────────────────────────────────────

describe('loadTournament — pre-fills from existing EventPost', function () {
    it('pre-fills wizard fields from an existing EventPost', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament();

        EventPost::create([
            'eventable_type' => Tournament::class,
            'eventable_id'   => $tournament->id,
            'type'           => ClubEventTypeEnum::TOURNAMENT,
            'title'          => 'Existing Title',
            'description'    => 'Existing description',
            'location'       => 'Salle A',
            'status'         => EventPostStatusEnum::PUBLISHED->value,
            'event_date'     => $tournament->start_date->toDateString(),
            'start_time'     => '10:00:00',
            'icon'           => '🏆',
        ]);

        mountWizard($admin, $tournament)
            ->assertSet('eventTitle', 'Existing Title')
            ->assertSet('eventDescription', 'Existing description')
            ->assertSet('eventLocation', 'Salle A')
            ->assertSet('eventStatus', 'PUBLISHED');
    });

    it('pre-fills eventTitle from tournament name when no EventPost exists', function () {
        $admin = User::factory()->create();
        $tournament = eventPostTournament(['name' => 'Summer Cup 2026']);

        mountWizard($admin, $tournament)
            ->assertSet('eventTitle', 'Summer Cup 2026');
    });
});

// ── Page publique ─────────────────────────────────────────────────────────────

describe('public events page', function () {
    it('shows published EventPosts', function () {
        $tournament = eventPostTournament();

        EventPost::create([
            'eventable_type' => Tournament::class,
            'eventable_id'   => $tournament->id,
            'type'           => ClubEventTypeEnum::TOURNAMENT,
            'title'          => 'Public Open 2026',
            'description'    => 'Open for all members.',
            'location'       => 'Club House',
            'status'         => EventPostStatusEnum::PUBLISHED->value,
            'event_date'     => now()->addMonth()->toDateString(),
            'start_time'     => '10:00:00',
            'icon'           => '🏆',
            'price'          => '10',
        ]);

        $this->get(route('eventPosts'))
            ->assertOk()
            ->assertSee('Public Open 2026')
            ->assertSee('10,00 €');
    });

    it('does not show draft EventPosts', function () {
        $tournament = eventPostTournament();

        EventPost::create([
            'eventable_type' => Tournament::class,
            'eventable_id'   => $tournament->id,
            'type'           => ClubEventTypeEnum::TOURNAMENT,
            'title'          => 'Hidden Draft',
            'description'    => '',
            'location'       => '',
            'status'         => EventPostStatusEnum::DRAFT->value,
            'event_date'     => now()->addMonth()->toDateString(),
            'start_time'     => '10:00:00',
            'icon'           => '🏆',
        ]);

        $this->get(route('eventPosts'))
            ->assertOk()
            ->assertDontSee('Hidden Draft');
    });

    it('shows Gratuit when price is zero', function () {
        $tournament = eventPostTournament(['price' => 0]);

        EventPost::create([
            'eventable_type' => Tournament::class,
            'eventable_id'   => $tournament->id,
            'type'           => ClubEventTypeEnum::TOURNAMENT,
            'title'          => 'Free Tournament',
            'description'    => '',
            'location'       => 'Club House',
            'status'         => EventPostStatusEnum::PUBLISHED->value,
            'event_date'     => now()->addMonth()->toDateString(),
            'start_time'     => '10:00:00',
            'icon'           => '🏆',
            'price'          => '0',
        ]);

        $this->get(route('eventPosts'))
            ->assertOk()
            ->assertSee('Gratuit');
    });
});
