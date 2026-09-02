<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;

/*
 * Fiche IC-4 de l'audit UX. L'en-tête de chaque équipe portait un « + » rond
 * dont l'infobulle mesurait 145 px sur un bouton de 30. Sur la dernière équipe
 * de la rangée, la bulle dépassait le cadre et donnait 46 px de défilement
 * horizontal à toute la page.
 *
 * Le balayage à 375 px ne l'avait jamais vue : Mary pose la classe
 * `lg:tooltip`, donc l'infobulle n'existe pas sous 1024 px. C'est un défaut
 * qui ne se voit qu'en grand écran et qu'il faut donc mesurer en grand écran.
 */
beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create();
    $season = makeActiveSeason();

    $ours = Club::factory()->create(['is_own_club' => true, 'name' => 'CTT Ottignies-Blocry']);
    $theirs = Club::factory()->create(['is_own_club' => false, 'name' => 'Royal Villette Charleroi TT']);

    foreach (['MEN' => ['A', 'B', 'C'], 'VETERANS' => ['A', 'B']] as $category => $names) {
        $league = League::factory()->create(['season_id' => $season->id, 'category' => $category]);

        foreach ($names as $name) {
            $team = ['season_id' => $season->id, 'league_id' => $league->id, 'captain_id' => $this->admin->id, 'name' => $name];
            $our = Team::factory()->create([...$team, 'club_id' => $ours->id]);
            $opponent = Team::factory()->create([...$team, 'club_id' => $theirs->id]);

            foreach (range(1, 4) as $week) {
                Interclub::factory()->create([
                    'season_id' => $season->id,
                    'league_id' => $league->id,
                    'visited_team_id' => $week % 2 ? $our->id : $opponent->id,
                    'visiting_team_id' => $week % 2 ? $opponent->id : $our->id,
                    'week_number' => $week,
                ]);
            }
        }
    }
});

it('never pushes the schedule past the right edge on a wide screen', function (): void {
    $this->actingAs($this->admin);

    $page = visit(route('admin.interclubs.interclubs'))->resize(1440, 900);

    $result = $page->script(<<<'JS'
    (() => {
      const viewport = document.documentElement.clientWidth;
      const spilling = [];

      for (const element of document.querySelectorAll('body *')) {
        const box = element.getBoundingClientRect();

        if (box.width === 0 || box.height === 0) continue;

        // Le tiroir de filtres et les modales fermées débordent par conception.
        if (element.closest('.drawer-side, dialog, [data-row-menu-panel]')) continue;

        if (box.right > viewport + 1) {
          spilling.push(Math.round(box.right - viewport) + 'px : <'
            + element.tagName.toLowerCase() + ' class="' + (element.className || '').toString().slice(0, 60) + '">');
        }

        if (spilling.length >= 6) break;
      }

      return {
        spilling,
        teams: document.querySelectorAll('[aria-expanded]').length,
      };
    })()
    JS);

    $state = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($state['teams'])->toBeGreaterThan(0, "La sonde doit voir les en-têtes d'équipe.");
    expect($state['spilling'])->toBe([], implode("\n", $state['spilling']));
});

it('names the action that used to hide behind a tooltip', function (): void {
    $this->actingAs($this->admin);

    visit(route('admin.interclubs.interclubs'))
        ->resize(1440, 900)
        ->assertSee(__('Add a match'));
});
