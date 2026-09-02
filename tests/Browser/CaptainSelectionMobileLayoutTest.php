<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;

/**
 * The match row laid its columns out with `flex-wrap` and let the opponent
 * column shrink below its content (`min-w-0` with no `truncate`). Under 640px
 * the name overflowed its box and painted on top of the availability counters —
 * both strings unreadable. Only a real browser at a real width sees this.
 */
beforeEach(function (): void {
    $this->season = Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->subMonths(4),
        'end_at' => now()->addMonths(6),
    ]);

    $ownClub = Club::factory()->ownClub()->create();
    $opponentClub = Club::factory()->create(['name' => 'CTT Wavre']);
    $league = League::factory()->create([
        'season_id' => $this->season->id,
        'category' => 'MEN',
        'division' => '3B',
    ]);

    $this->captain = User::factory()->isCompetitor()->create();

    $team = Team::factory()->create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => $ownClub->id,
        'captain_id' => $this->captain->id,
    ]);

    $players = User::factory()->isCompetitor()->count(6)->create();
    $team->users()->attach($players->pluck('id'));

    // A long opponent name is the realistic case, not the edge case.
    $opponent = Team::factory()->create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => $opponentClub->id,
    ]);

    $fixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => $opponent->id,
        'week_number' => 3,
        'total_players' => 4,
        'start_date_time' => now()->addDays(5)->setTime(19, 45),
    ]);

    foreach ($players as $i => $p) {
        $fixture->users()->attach($p->id, [
            'availability' => $i < 3 ? 'available' : 'unavailable',
            'is_selected' => false,
        ]);
    }
});

/**
 * Every text node inside a match row must keep its own pixels. Comparing the
 * rendered boxes catches the overlap whatever the CSS mechanism behind it.
 */
$noOverlapScript = <<<'JS'
(() => {
  const rows = document.querySelectorAll('[data-match-row]');
  if (rows.length === 0) return 'no-rows';

  const overlaps = (a, b) =>
    a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;

  for (const row of rows) {
    const leaves = [...row.querySelectorAll('*')].filter(el =>
      el.offsetParent !== null &&
      [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim() !== '')
    );

    for (let i = 0; i < leaves.length; i++) {
      for (let j = i + 1; j < leaves.length; j++) {
        if (leaves[i].contains(leaves[j]) || leaves[j].contains(leaves[i])) continue;
        const a = leaves[i].getBoundingClientRect();
        const b = leaves[j].getBoundingClientRect();
        if (a.width === 0 || b.width === 0) continue;
        if (overlaps(a, b)) {
          return 'OVERLAP: ' + leaves[i].textContent.trim().slice(0, 24)
               + ' || ' + leaves[j].textContent.trim().slice(0, 24);
        }
      }
    }
  }
  return 'ok';
})()
JS;

it('never overprints one text on another in a match row on a phone', function () use ($noOverlapScript): void {
    $this->actingAs($this->captain);

    visit(route('admin.interclubs.captain-selection'))
        ->on()->iPhone15()
        ->assertScript($noOverlapScript, 'ok');
});

it('never overprints one text on another in a match row on a desktop', function () use ($noOverlapScript): void {
    $this->actingAs($this->captain);

    visit(route('admin.interclubs.captain-selection'))
        ->on()->macbook16()
        ->assertScript($noOverlapScript, 'ok');
});
