<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\ClubEventTypeEnum;
use App\Domains\Shared\Enums\EventPostStatusEnum;
use App\Models\ClubEvents\Interclub\Club;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubPosts\EventPost;

// ── Setup : la homepage nécessite un Club correspondant à ourClub() ───────────
beforeEach(function (): void {
    Club::factory()->create(['licence' => config('app.club_licence')]);
});

// ── Helper ────────────────────────────────────────────────────────────────────

function featuredHomepageEvent(array $overrides = []): EventPost
{
    $tournament = Tournament::factory()->create();

    return EventPost::create(array_merge([
        'eventable_type' => Tournament::class,
        'eventable_id' => $tournament->id,
        'type' => ClubEventTypeEnum::TOURNAMENT,
        'title' => 'Événement homepage',
        'description' => 'Description.',
        'location' => 'Salle A',
        'status' => EventPostStatusEnum::PUBLISHED->value,
        'event_date' => today()->addWeek()->toDateString(),
        'start_time' => '09:00:00',
        'icon' => '🏆',
        'featured' => true,
        'featured_until' => null,
    ], $overrides));
}

// ── Bloc présent / absent ─────────────────────────────────────────────────────

describe('bloc featured sur la homepage', function () {
    it("n'affiche pas le bloc quand aucun événement n'est featured", function () {
        $this->get('/')->assertOk()->assertDontSee('Événements à venir');
    });

    it('affiche le bloc quand un événement est featured sans date de fin', function () {
        featuredHomepageEvent(['title' => 'Tournoi printanier']);

        $this->get('/')->assertOk()->assertSee('Tournoi printanier');
    });

    it("affiche l'événement quand featured_until est aujourd'hui", function () {
        featuredHomepageEvent(['title' => 'Tournoi du jour', 'featured_until' => today()->toDateString()]);

        $this->get('/')->assertOk()->assertSee('Tournoi du jour');
    });

    it('affiche un événement featured until demain', function () {
        featuredHomepageEvent(['title' => 'Tournoi demain', 'featured_until' => today()->addDay()->toDateString()]);

        $this->get('/')->assertOk()->assertSee('Tournoi demain');
    });
});

// ── Expiration ────────────────────────────────────────────────────────────────

describe('expiration du featuring', function () {
    it("masque l'événement quand featured_until était hier", function () {
        featuredHomepageEvent(['title' => 'Tournoi expiré', 'featured_until' => today()->subDay()->toDateString()]);

        $this->get('/')->assertOk()->assertDontSee('Tournoi expiré');
    });

    it('masque le bloc entier quand tous les événements sont expirés', function () {
        featuredHomepageEvent(['title' => 'Expiré A', 'featured_until' => today()->subDay()->toDateString()]);
        featuredHomepageEvent(['title' => 'Expiré B', 'featured_until' => today()->subWeek()->toDateString()]);

        $this->get('/')->assertOk()
            ->assertDontSee('Événements à venir')
            ->assertDontSee('Expiré A')
            ->assertDontSee('Expiré B');
    });

    it("masque l'événement non publié même featured", function () {
        featuredHomepageEvent([
            'title' => 'Brouillon featured',
            'status' => EventPostStatusEnum::DRAFT->value,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Brouillon featured');
    });
});

// ── Contenu de la carte ───────────────────────────────────────────────────────

describe('contenu affiché dans la carte', function () {
    it('affiche le titre, le lieu et la date', function () {
        featuredHomepageEvent([
            'title' => 'Stage été 2026',
            'location' => 'Salle Blocry',
        ]);

        $this->get('/')->assertOk()
            ->assertSee('Stage été 2026')
            ->assertSee('Salle Blocry');
    });
});
