<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
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

// ── Cas 1 : Interclub enabled + saison active ────────────────────────────────

/*
 * La page d'accueil ne rend plus la ligne interclubs figée que ces cas
 * visaient : l'agenda daté annonce désormais chaque match à domicile par son
 * adversaire et sa date. Les réglages AppSetting ne survivent que pour une
 * chose — la mention « interclubs à domicile le vendredi » de la ligne « notre
 * rythme habituel ». C'est donc elle que ces cas vérifient.
 *
 * Deux réglages ne sont plus lus par la page publique : la description et le
 * lieu. Ils restent éditables depuis l'admin (cas 6 et 7 ci-dessous) et n'ont
 * plus d'assertion côté site.
 */

describe('interclub activé avec une saison active', function (): void {
    it('mentionne les interclubs dans le rythme habituel', function (): void {
        activeSeasonForInterclub();

        // defaults (no AppSetting rows) → enabled by default
        $this->get('/')
            ->assertOk()
            ->assertSee('interclubs à domicile le vendredi');
    });
});

// ── Cas 2 : Interclub disabled ────────────────────────────────────────────────

describe('interclub désactivé', function (): void {
    it("ne mentionne pas les interclubs quand la clé est '0'", function (): void {
        activeSeasonForInterclub();
        AppSetting::set('interclub_schedule_enabled', '0');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('interclubs à domicile');
    });
});

// ── Cas 3 : Interclub enabled mais aucune saison ──────────────────────────────

describe('interclub activé mais aucune saison', function (): void {
    it("ne mentionne pas les interclubs quand il n'y a pas de saison", function (): void {
        AppSetting::set('interclub_schedule_enabled', '1');

        $this->get('/')
            ->assertOk()
            ->assertDontSee('interclubs à domicile');
    });
});

// ── Cas 4 : Jour personnalisé ─────────────────────────────────────────────────

describe('jour personnalisé', function (): void {
    it('reprend un jour personnalisé "Samedi" dans le rythme', function (): void {
        activeSeasonForInterclub();
        AppSetting::set('interclub_schedule_enabled', '1');
        AppSetting::set('interclub_schedule_day', 'Samedi');

        $this->get('/')
            ->assertOk()
            ->assertSee('interclubs à domicile le samedi')
            ->assertDontSee('interclubs à domicile le vendredi');
    });
});

// ── Cas 5 : Heure personnalisée ───────────────────────────────────────────────

describe('heure personnalisée', function (): void {
    it("reprend l'heure de début personnalisée dans le rythme", function (): void {
        activeSeasonForInterclub();
        AppSetting::set('interclub_schedule_enabled', '1');
        AppSetting::set('interclub_schedule_time_start', '18:00');

        $this->get('/')
            ->assertOk()
            ->assertSee('18h00');
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
