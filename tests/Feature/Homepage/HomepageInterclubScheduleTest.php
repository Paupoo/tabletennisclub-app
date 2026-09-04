<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Models\AppSetting;
use App\Domains\Trainings\Models\TrainingPack;
use Livewire\Livewire;

// ── Setup ────────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    Club::factory()->ownClub()->create(['email_contact' => 'club@test.com']);
});

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Crée une saison active (start_at passé) avec un pack actif.
 *
 * @param  array<string, mixed>  $seasonOverrides
 */
function activeSeasonForInterclub(array $seasonOverrides = []): Season
{
    $startAt = now()->subMonths(2)->startOfMonth();

    $season = Season::factory()->create(array_merge([
        'is_active' => true,
        'start_at' => $startAt,
        'end_at' => $startAt->copy()->addYear(),
    ], $seasonOverrides));

    TrainingPack::factory()->create([
        'season_id' => $season->id,
        'is_active' => true,
        'day_of_week' => 3, // Mercredi
        'start_time' => '19:00:00',
        'duration_minutes' => 90,
        'name' => 'Pack Mercredi',
    ]);

    return $season;
}

// ── Les interclubs sur la page publique ─────────────────────────────────────

/*
 * La page d'accueil n'affiche plus de ligne interclubs figée. Elle annonce les
 * vrais matches à domicile, à leur date, dans la grille des activités.
 *
 * Les six réglages `interclub_schedule_*` n'ont donc plus aucun effet public :
 * ils restent éditables depuis l'admin (cas 6 et 7 ci-dessous) mais ne
 * pilotent plus rien sur le site. Les cas qui les vérifiaient côté public ont
 * disparu avec leur sujet.
 */

describe('interclubs sur la page publique', function (): void {
    it('annonce un match à domicile à sa date', function (): void {
        $season = activeSeasonForInterclub();

        $rival = Club::factory()->create(['is_own_club' => false, 'name' => 'Arc-en-Ciel CTT']);
        $ourTeam = Team::factory()->create([
            'club_id' => Club::ourClub()->value('id'), 'season_id' => $season->id, 'name' => 'A',
        ]);
        $theirTeam = Team::factory()->create([
            'club_id' => $rival->id, 'season_id' => $season->id, 'name' => 'F',
        ]);

        Interclub::factory()->create([
            'season_id' => $season->id,
            'visited_team_id' => $ourTeam->id,
            'visiting_team_id' => $theirTeam->id,
            'start_date_time' => now()->addDays(3)->setTime(20, 0),
            'is_bye' => false,
        ]);

        $this->get('/')->assertOk()->assertSee('Arc-en-Ciel');
    });

    it('tait un match joué à l\'extérieur', function (): void {
        $season = activeSeasonForInterclub();

        $rival = Club::factory()->create(['is_own_club' => false, 'name' => 'Nivelloise']);
        $ourTeam = Team::factory()->create([
            'club_id' => Club::ourClub()->value('id'), 'season_id' => $season->id, 'name' => 'A',
        ]);
        $theirTeam = Team::factory()->create([
            'club_id' => $rival->id, 'season_id' => $season->id, 'name' => 'D',
        ]);

        Interclub::factory()->create([
            'season_id' => $season->id,
            'visited_team_id' => $theirTeam->id,
            'visiting_team_id' => $ourTeam->id,
            'start_date_time' => now()->addDays(3)->setTime(20, 0),
            'is_bye' => false,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Nivelloise');
    });

    it('ne laisse plus les réglages interclubs peser sur la page publique', function (): void {
        activeSeasonForInterclub();
        AppSetting::set('interclub_schedule_description', 'Matches de compétition à domicile');

        $this->get('/')->assertOk()->assertDontSee('Matches de compétition à domicile');
    });
});

// ── Cas 6 : Page admin club-info charge sans erreur ──────────────────────────

describe('admin club-info', function (): void {
    it('charge la page admin club-info sans erreur', function (): void {
        Livewire::test('pages::club-admin.club-info')
            ->assertStatus(200);
    });

    it('charge les valeurs AppSetting dans mount()', function (): void {
        AppSetting::set('interclub_schedule_enabled', '1');
        AppSetting::set('interclub_schedule_day', 'Samedi');
        AppSetting::set('interclub_schedule_time_start', '18:30');
        AppSetting::set('interclub_schedule_time_end', '22:30');
        AppSetting::set('interclub_schedule_location', 'Salle Test');
        AppSetting::set('interclub_schedule_description', 'Description test');

        Livewire::test('pages::club-admin.club-info')
            ->assertSet('interclubEnabled', true)
            ->assertSet('interclubDay', 'Samedi')
            ->assertSet('interclubTimeStart', '18:30')
            ->assertSet('interclubTimeEnd', '22:30')
            ->assertSet('interclubLocation', 'Salle Test')
            ->assertSet('interclubDescription', 'Description test');
    });
});

// ── Cas 7 : Saving les settings Interclub ────────────────────────────────────

describe('save des settings interclub', function (): void {
    it('met à jour AppSetting après save()', function (): void {
        Livewire::test('pages::club-admin.club-info')
            ->set('interclubEnabled', false)
            ->set('interclubDay', 'Dimanche')
            ->set('interclubTimeStart', '14:00')
            ->set('interclubTimeEnd', '18:00')
            ->set('interclubLocation', 'Nouvelle Salle')
            ->set('interclubDescription', 'Nouvelle description')
            ->call('save');

        expect(AppSetting::get('interclub_schedule_enabled'))->toBe('0')
            ->and(AppSetting::get('interclub_schedule_day'))->toBe('Dimanche')
            ->and(AppSetting::get('interclub_schedule_time_start'))->toBe('14:00')
            ->and(AppSetting::get('interclub_schedule_time_end'))->toBe('18:00')
            ->and(AppSetting::get('interclub_schedule_location'))->toBe('Nouvelle Salle')
            ->and(AppSetting::get('interclub_schedule_description'))->toBe('Nouvelle description');
    });
})->group('interclub-settings');
