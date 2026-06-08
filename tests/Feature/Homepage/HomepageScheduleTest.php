<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;

// ── Setup : la homepage nécessite un Club correspondant à ourClub() ───────────
beforeEach(function (): void {
    Club::factory()->ownClub()->create();
});

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Crée une saison avec un pack actif ayant un jour de semaine défini.
 *
 * @param  array<string, mixed>  $seasonOverrides
 * @param  array<string, mixed>  $packOverrides
 */
function seasonWithActivePack(array $seasonOverrides = [], array $packOverrides = []): Season
{
    $season = Season::factory()->create($seasonOverrides);

    TrainingPack::factory()->create(array_merge([
        'season_id' => $season->id,
        'is_active' => true,
        'day_of_week' => 3, // Mercredi
        'start_time' => '19:00:00',
        'duration_minutes' => 90,
        'name' => 'Pack Test Mercredi',
    ], $packOverrides));

    return $season;
}

// ── Cas 1 : Aucune saison ─────────────────────────────────────────────────────

describe('aucune saison', function (): void {
    it('charge la homepage avec le calendrier vide', function (): void {
        $this->get('/')->assertOk()->assertDontSee('Ces horaires entrent en vigueur');
    });
});

// ── Cas 2 : Saison future ─────────────────────────────────────────────────────

describe('saison future (is_active=false, start_at dans le futur)', function (): void {
    it('affiche le pack et le bandeau "dès le"', function (): void {
        $startAt = now()->addMonths(2)->startOfMonth();

        seasonWithActivePack([
            'is_active' => false,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addYear(),
        ], [
            'name' => 'Pack Futur Mercredi',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Pack Futur Mercredi')
            ->assertSee('Ces horaires entrent en vigueur');
    });
});

// ── Cas 3 : Saison active, start_at dans le passé ────────────────────────────

describe('saison active avec start_at dans le passé', function (): void {
    it('affiche le pack sans bandeau', function (): void {
        $startAt = now()->subMonths(2)->startOfMonth();

        seasonWithActivePack([
            'is_active' => true,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addYear(),
        ], [
            'name' => 'Pack Actif Passé',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Pack Actif Passé')
            ->assertDontSee('Ces horaires entrent en vigueur')
            ->assertDontSee('Saison terminée');
    });
});

// ── Cas 4 : Saison active, start_at dans le futur ────────────────────────────

describe('saison active avec start_at dans le futur', function (): void {
    it('affiche le pack et le bandeau "dès le"', function (): void {
        $startAt = now()->addMonths(3)->startOfMonth();

        seasonWithActivePack([
            'is_active' => true,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addYear(),
        ], [
            'name' => 'Pack Actif Futur',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Pack Actif Futur')
            ->assertSee('Ces horaires entrent en vigueur');
    });
});

// ── Cas 5 : Aucune saison active, saison passée avec packs ───────────────────

describe('aucune saison active, saison passée avec packs', function (): void {
    it('affiche le pack avec le bandeau "Saison terminée"', function (): void {
        $startAt = now()->subYears(2)->startOfMonth();

        seasonWithActivePack([
            'is_active' => false,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addMonths(10),
        ], [
            'name' => 'Pack Saison Passée',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Pack Saison Passée')
            ->assertSee('Saison terminée');
    });
});

// ── Cas 6 & 7 : Filtrage par pack_end_date ───────────────────────────────────

describe('filtrage par pack_end_date', function (): void {
    it('exclut un pack dont pack_end_date est hier', function (): void {
        $startAt = now()->subMonths(2)->startOfMonth();

        seasonWithActivePack([
            'is_active' => true,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addYear(),
        ], [
            'name' => 'Pack Expiré Hier',
            'pack_end_date' => today()->subDay()->toDateString(),
        ]);

        $this->get('/')->assertOk()->assertDontSee('Pack Expiré Hier');
    });

    it("inclut un pack dont pack_end_date est aujourd'hui", function (): void {
        $startAt = now()->subMonths(2)->startOfMonth();

        seasonWithActivePack([
            'is_active' => true,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addYear(),
        ], [
            'name' => 'Pack Expire Aujd',
            'pack_end_date' => today()->toDateString(),
        ]);

        $this->get('/')->assertOk()->assertSee('Pack Expire Aujd');
    });
});

// ── Cas 8 : Pack sans day_of_week ────────────────────────────────────────────

describe('pack sans day_of_week', function (): void {
    it('exclut un pack sans day_of_week du calendrier', function (): void {
        $startAt = now()->subMonths(2)->startOfMonth();

        $season = Season::factory()->create([
            'is_active' => true,
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addYear(),
        ]);

        TrainingPack::factory()->create([
            'season_id' => $season->id,
            'is_active' => true,
            'day_of_week' => null,
            'name' => 'Pack Sans Jour',
            'start_time' => '19:00:00',
            'duration_minutes' => 90,
        ]);

        $this->get('/')->assertOk()->assertDontSee('Pack Sans Jour');
    });
});

// ── Cas 9 : Priorité future > passée ─────────────────────────────────────────

describe('priorité de saison', function (): void {
    it('affiche la saison future plutôt que la saison passée quand les deux existent', function (): void {
        // Saison passée avec pack
        $pastStart = now()->subYears(2)->startOfMonth();
        seasonWithActivePack([
            'is_active' => false,
            'start_at' => $pastStart,
            'end_at' => $pastStart->copy()->addMonths(10),
        ], [
            'name' => 'Pack Saison Passée Priority',
        ]);

        // Saison future avec pack
        $futureStart = now()->addMonths(2)->startOfMonth();
        seasonWithActivePack([
            'is_active' => false,
            'start_at' => $futureStart,
            'end_at' => $futureStart->copy()->addYear(),
        ], [
            'name' => 'Pack Saison Future Priority',
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Pack Saison Future Priority')
            ->assertDontSee('Pack Saison Passée Priority')
            ->assertSee('Ces horaires entrent en vigueur');
    });
});
