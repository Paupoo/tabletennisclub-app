<?php

declare(strict_types=1);

use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Trainings\Models\TrainingPack;

/**
 * La résolution de saison et ses bandeaux.
 *
 * Ces cas s'appuyaient sur le nom du pack comme révélateur, puis sur la plage
 * horaire de la ligne « notre rythme habituel ». Ni l'un ni l'autre n'est plus
 * rendu : la page n'affiche que les activités datées. Ce qui reste observable —
 * et ce que ces cas testent réellement — ce sont les bandeaux de saison.
 *
 * Trois cas ont disparu avec leur sujet : le filtrage des packs par
 * `pack_end_date` et par `day_of_week` ne se voit plus nulle part sur la page
 * publique, puisque les packs n'y sont plus lus.
 */

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
    it('affiche le bandeau "dès le" pour une saison future', function (): void {
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
            ->assertSee('Ces horaires entrent en vigueur');
    });
});

// ── Cas 3 : Saison active, start_at dans le passé ────────────────────────────

describe('saison active avec start_at dans le passé', function (): void {
    it('n’affiche aucun bandeau pour une saison active déjà commencée', function (): void {
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
            ->assertDontSee('Ces horaires entrent en vigueur')
            ->assertDontSee('Saison terminée');
    });
});

// ── Cas 4 : Saison active, start_at dans le futur ────────────────────────────

describe('saison active avec start_at dans le futur', function (): void {
    it('affiche le bandeau "dès le" pour une saison active pas encore commencée', function (): void {
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
            ->assertSee('Ces horaires entrent en vigueur');
    });
});

// ── Cas 5 : Aucune saison active, saison passée avec packs ───────────────────

describe('aucune saison active, saison passée avec packs', function (): void {
    it('affiche le bandeau "Saison terminée"', function (): void {
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
            ->assertSee('Saison terminée');
    });
});

// ── Cas 9 : Priorité future > passée ─────────────────────────────────────────

describe('priorité de saison', function (): void {
    it('retient la saison future plutôt que la passée quand les deux existent', function (): void {
        // Saison passée avec pack — horaire distinct pour pouvoir la reconnaître.
        $pastStart = now()->subYears(2)->startOfMonth();
        seasonWithActivePack([
            'is_active' => false,
            'start_at' => $pastStart,
            'end_at' => $pastStart->copy()->addMonths(10),
        ], [
            'name' => 'Pack Saison Passée Priority',
            'start_time' => '17:00:00',
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
            ->assertSee('Ces horaires entrent en vigueur');
    });
});
