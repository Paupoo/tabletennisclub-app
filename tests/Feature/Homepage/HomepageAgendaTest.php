<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Club\Models\Room;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingStatusEnum;
use App\Domains\Shared\Enums\MeetingTypeEnum;
use App\Domains\Trainings\Models\Training;
use App\Domains\Trainings\Models\TrainingPack;
use Carbon\CarbonImmutable;

/**
 * Le seam de rendu : ce qu'un visiteur lit réellement sur la page d'accueil.
 * La sélection des activités est vérifiée par {@see PublicAgendaTest}.
 */
beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-09-04 10:00:00');
    Club::factory()->ownClub()->create();

    $this->season = Season::factory()->create([
        'is_active' => true,
        'start_at' => '2026-09-01',
        'end_at' => '2027-06-30',
    ]);

    $this->room = Room::factory()->create(['name' => 'Demeester -1']);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('affiche une séance datée avec son horaire et sa salle', function (): void {
    $pack = TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $this->room->id, 'is_active' => true,
        'name' => 'Lundi — Entraînement supervisé', 'day_of_week' => 1,
        'start_time' => '18:00:00', 'duration_minutes' => 120,
    ]);

    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $this->room->id,
        'training_pack_id' => $pack->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Entraînement supervisé')
        ->assertDontSee('Lundi — Entraînement supervisé', false)
        ->assertSee('18h00');
});

it('annonce une séance annulée, son badge et son motif', function (): void {
    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $this->room->id,
        'start' => '2026-09-15 20:30:00',
        'end' => '2026-09-15 22:00:00',
        'status' => 'cancelled_closed',
        'cancellation_note' => 'Assemblée générale — merci de ne pas vous déplacer',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Salle fermée')
        ->assertSee('Assemblée générale — merci de ne pas vous déplacer', false);
});

it('distingue à l’écran la salle laissée ouverte en jeu libre', function (): void {
    Training::factory()->cancelledFree()->create([
        'season_id' => $this->season->id,
        'room_id' => $this->room->id,
        'start' => '2026-09-14 18:00:00',
        'end' => '2026-09-14 20:00:00',
        'cancellation_note' => 'Coach absent',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Salle ouverte en libre')
        ->assertDontSee('Salle fermée');
});

it('garde les réunions de comité hors de la page publique', function (): void {
    Meeting::factory()->create([
        'type' => MeetingTypeEnum::COMMITTEE,
        'status' => MeetingStatusEnum::CONFIRMED,
        'title' => 'Réunion de comité — septembre',
        'scheduled_at' => '2026-09-16 18:00:00',
    ]);

    $this->get('/')->assertOk()->assertDontSee('Réunion de comité — septembre', false);
});

it('rend les cinq semaines de la grille, pas seulement les jours proches', function (): void {
    $pack = TrainingPack::factory()->create([
        'season_id' => $this->season->id, 'room_id' => $this->room->id, 'is_active' => true,
        'name' => 'Mardi — Perfectionnement', 'day_of_week' => 2,
        'start_time' => '20:30:00', 'duration_minutes' => 90,
    ]);

    // La première est dans la semaine en cours, la seconde à trois semaines.
    foreach (['09-08', '09-29'] as $day) {
        Training::factory()->create([
            'season_id' => $this->season->id,
            'room_id' => $this->room->id,
            'training_pack_id' => $pack->id,
            'type' => 'Directed',
            'start' => "2026-{$day} 20:30:00",
            'end' => "2026-{$day} 22:00:00",
        ]);
    }

    $response = $this->get('/')->assertOk();

    // Une fois dans la grille, une fois dans la liste mobile pour la plus proche.
    expect(substr_count($response->getContent(), 'Perfectionnement'))->toBeGreaterThanOrEqual(3);

    $response->assertSee('29');
});

it('dessine les sept jours de la semaine en en-tête de grille', function (): void {
    // La grille ne se dessine que si le club a quelque chose à annoncer.
    Training::factory()->create([
        'season_id' => $this->season->id,
        'room_id' => $this->room->id,
        'start' => '2026-09-07 18:00:00',
        'end' => '2026-09-07 20:00:00',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('lundi')
        ->assertSee('dimanche');
});
