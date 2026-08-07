<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $this->season = makeActiveSeason();
});

it('public results page loads without JS errors', function (): void {
    visit(route('results'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Résultats');
});

it('admin interclub results page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.results'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Résultats');
});

it('captain selection page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.captain-selection'))
        ->assertNoJavaScriptErrors()
        ->assertSee('Sélections');
});

it('control center page loads without JS errors', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.control-center'))
        ->assertNoJavaScriptErrors();
});

/*
 * Fiche IC-5 de l'audit UX. Sur la base peuplée, les neuf équipes s'ouvraient
 * d'emblée : 164 lignes, 8 811 px, 9,8 hauteurs d'écran, contre 1,6 à 2,2
 * partout ailleurs. Comparer l'équipe A à l'équipe D demandait de mémoriser un
 * bilan sur sept écrans de défilement — Nielsen nº 6.
 *
 * L'écran frère, la liste des rencontres, part replié depuis toujours : deux
 * écrans jumeaux, deux comportements opposés (Nielsen nº 4).
 *
 * Le bilan (victoires, défaites, ratio, position finale) vit dans l'en-tête de
 * la carte, pas dans la zone repliable : replier ne cache donc rien de ce qu'on
 * vient comparer.
 */
it('opens the results screen with every team folded', function (): void {
    $this->actingAs($this->admin);

    $club = Club::factory()->create(['is_own_club' => true]);
    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);

    foreach (['A', 'B'] as $name) {
        Team::factory()->create([
            'season_id' => $this->season->id,
            'league_id' => $league->id,
            'club_id' => $club->id,
            'captain_id' => $this->admin->id,
            'name' => $name,
        ]);
    }

    $page = visit(route('admin.interclubs.results'));

    $result = $page->script(<<<'JS'
    (() => {
      const toggles = [...document.querySelectorAll('[aria-expanded]')]
        .filter((el) => (el.getAttribute('aria-label') || '').length > 0);

      return {
        toggles: toggles.length,
        expanded: toggles.filter((el) => el.getAttribute('aria-expanded') === 'true').length,
      };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['toggles'])->toBeGreaterThan(0, 'La sonde doit voir les dépliants d\'équipe.');
    expect($state['expanded'])->toBe(0, 'Chaque équipe doit arriver repliée : le bilan reste dans l\'en-tête.');
});

/*
 * Fiche KB-2. <x-section-accordion> faisait bien l'essentiel — un vrai <button>,
 * atteignable au clavier — mais n'exposait pas son état : un lecteur d'écran
 * annonçait « Hommes, 2 équipes, bouton » sans jamais dire si la section était
 * ouverte. Le chevron qui pivote ne dit rien à qui ne le voit pas.
 *
 * WCAG 2.2 nº 4.1.2 « Nom, rôle, valeur », niveau A. Le composant est partagé :
 * le corriger une fois corrige tous les écrans qui l'emploient.
 */
it('tells assistive tech whether a category section is open', function (): void {
    $this->actingAs($this->admin);

    $club = Club::factory()->create(['is_own_club' => true]);
    $league = League::factory()->create(['season_id' => $this->season->id, 'category' => 'MEN']);
    Team::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => $club->id,
        'captain_id' => $this->admin->id,
        'name' => 'A',
    ]);

    $page = visit(route('admin.interclubs.results'));

    $result = $page->script(<<<'JS'
    (() => {
      const sections = [...document.querySelectorAll('section > button')];

      return {
        sections: sections.length,
        stated: sections.filter((el) => el.hasAttribute('aria-expanded')).length,
      };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['sections'])->toBeGreaterThan(0, "La sonde doit voir l'accordéon de catégorie.");
    expect($state['stated'])->toBe($state['sections'], "Chaque accordéon doit annoncer s'il est ouvert.");
});
