<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Team;

/**
 * Le menu « Plus » d'une ligne de match s'ouvrait sous un conteneur en
 * `overflow-hidden`, posé là pour arrondir les coins de la liste : le panneau
 * était coupé. Même piège que le roster d'entraînement, autre conteneur.
 */
it('opens the match row actions without the list clipping them', function (): void {
    $season = makeActiveSeason();
    $league = League::factory()->create(['season_id' => $season->id, 'category' => 'MEN']);
    $captain = User::factory()->isCompetitor()->create();

    $team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'captain_id' => $captain->id,
        'club_id' => Club::factory()->ownClub()->create()->id,
    ]);
    $team->users()->attach($captain->id);

    Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'total_players' => 4,
        'is_bye' => false,
        'start_date_time' => now()->addDays(5),
    ]);

    $this->actingAs($captain);

    $page = visit(route('admin.interclubs.captain-selection'))->resize(1440, 900);

    $page->assertNoJavaScriptErrors()
        ->wait(1)
        ->click('[data-match-row] [data-row-menu-trigger]')
        ->wait(1);

    $probe = $page->script(<<<'JS'
        (() => {
          const panel = [...document.querySelectorAll('[data-row-menu-panel]')]
            .find((e) => e.getClientRects().length > 0);
          if (!panel) return { found: false };
          const r = panel.getBoundingClientRect();
          const x = Math.round(r.left + r.width / 2);
          const hit = (y) => {
            const e = document.elementFromPoint(x, Math.round(y));
            return e !== null && panel.contains(e);
          };

          const clippers = [];
          for (let el = panel.parentElement; el && el !== document.documentElement; el = el.parentElement) {
            const cs = getComputedStyle(el);
            if (cs.overflowX !== 'visible' || cs.overflowY !== 'visible') {
              clippers.push(el.tagName.toLowerCase() + '.' + (el.className || '').toString().slice(0, 60));
            }
          }

          return {
            found: true,
            reachable: hit(r.top + 8) && hit(r.top + r.height / 2) && hit(r.bottom - 8),
            clippers: clippers.filter((c) => ! c.startsWith('body.')),
          };
        })()
    JS);

    $p = $probe[0] ?? $probe;

    expect($p['found'])->toBeTrue('la ligne doit proposer ses actions');
    expect($p['clippers'])->toBe([], 'un ancêtre du panneau rogne : ' . implode(', ', $p['clippers']));
    expect($p['reachable'])->toBeTrue('le panneau est rogné — son bas n\'est pas cliquable');
});
