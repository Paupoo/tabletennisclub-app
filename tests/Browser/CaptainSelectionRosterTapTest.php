<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;

/**
 * A roster row lit up on hover like something clickable, but only the 44px
 * square at its far right toggled anything: tapping the name, the rank or the
 * stats did nothing, and nothing said so. The row cannot become a <button> —
 * it carries tel: and mailto: links, which no button or label may wrap — so
 * the label's ::before is stretched over the card instead and the links are
 * raised above it.
 *
 * Both halves of that only exist in a real browser: whether the overlay
 * actually covers the card, and whether the links are still the topmost thing
 * at their own coordinates. Measured rather than clicked — a click on an
 * element the runner considers hidden or ambiguous hangs this suite instead of
 * failing it.
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

    // Contact details are the whole reason the row cannot be a button, so the
    // roster has to carry them.
    $players = User::factory()->isCompetitor()->count(5)->create([
        'phone_number' => '0470 12 34 56',
    ]);
    $team->users()->attach($players->pluck('id'));

    $opponent = Team::factory()->create([
        'name' => 'A',
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'club_id' => $opponentClub->id,
    ]);

    $this->fixture = Interclub::factory()->create([
        'season_id' => $this->season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'visiting_team_id' => $opponent->id,
        'week_number' => 3,
        'total_players' => 4,
        'start_date_time' => now()->addDays(5)->setTime(19, 45),
    ]);

    foreach ($players as $p) {
        $this->fixture->users()->attach($p->id, [
            'availability' => 'available',
            'is_selected' => false,
        ]);
    }
});

/**
 * The command must answer across the whole card, not in one corner of it.
 */
$tapTargetScript = <<<'JS'
(() => {
  const rows = document.querySelectorAll('[data-roster-row]');
  if (rows.length === 0) return 'no-rows';

  // elementFromPoint only answers inside the viewport, so a row scrolled past
  // the fold reports nothing rather than a dead zone. Rows are probed where
  // they are actually painted; the counter below makes sure that was not none
  // of them.
  const inView = (box) =>
    box.top >= 0 && box.bottom <= window.innerHeight && box.height > 0;

  let probed = 0;

  for (const row of rows) {
    const toggle = row.querySelector('[data-roster-toggle]');
    if (!toggle) return 'no-toggle';

    const rowBox = row.getBoundingClientRect();
    const overlay = getComputedStyle(toggle, '::before');

    if (overlay.position !== 'absolute') return 'overlay-not-absolute';
    if (!inView(rowBox)) continue;

    probed++;

    // Three points that used to answer nothing: over the rank chip, over the
    // name, and in the gap between the stats and the checkbox. The contact
    // links are deliberately excluded — they are supposed to win their own
    // pixels, and the other test is the one that checks they still do.
    const probes = [
      [rowBox.left + 20, rowBox.top + rowBox.height / 2],
      [rowBox.left + rowBox.width * 0.45, rowBox.top + 14],
      [rowBox.left + rowBox.width * 0.45, rowBox.bottom - 6],
    ];

    for (const [x, y] of probes) {
      const hit = document.elementFromPoint(x, y);

      if (hit === null) return 'nothing-at-point';
      if (hit.closest('[data-roster-contact]')) continue;
      if (hit !== toggle && !toggle.contains(hit)) {
        return 'DEAD ZONE: ' + hit.tagName + '.' + hit.className.toString().slice(0, 50);
      }
    }
  }

  return probed === 0 ? 'no-row-in-view' : 'ok';
})()
JS;

/**
 * And the two links it covers must stay reachable, or the overlay has traded
 * one broken promise for another.
 */
$contactsOnTopScript = <<<'JS'
(() => {
  const links = document.querySelectorAll('[data-roster-contact]');
  if (links.length === 0) return 'no-contacts';

  let probed = 0;

  for (const link of links) {
    const box = link.getBoundingClientRect();
    if (box.width === 0 || box.height === 0) continue;
    if (box.top < 0 || box.bottom > window.innerHeight) continue;

    probed++;

    const hit = document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2);
    if (hit !== link && !link.contains(hit)) {
      return 'BURIED: ' + link.getAttribute('href')
           + ' under ' + (hit ? hit.tagName + '.' + hit.className.toString().slice(0, 50) : 'null');
    }
  }

  return probed === 0 ? 'no-contact-in-view' : 'ok';
})()
JS;

it('answers a tap anywhere on a player row, on a phone', function () use ($tapTargetScript): void {
    $this->actingAs($this->captain);

    visit(route('admin.interclubs.captain-selection'))
        ->on()->iPhone15()
        ->click('[data-row-menu-primary]')
        ->wait(1)
        ->assertPresent('[data-roster-row]')
        ->assertScript($tapTargetScript, 'ok');
});

it('answers a tap anywhere on a player row, on a desktop', function () use ($tapTargetScript): void {
    $this->actingAs($this->captain);

    visit(route('admin.interclubs.captain-selection'))
        ->on()->macbook16()
        ->click('[data-row-menu-primary]')
        ->wait(1)
        ->assertPresent('[data-roster-row]')
        ->assertScript($tapTargetScript, 'ok');
});

it('keeps the contact links reachable under the stretched target', function () use ($contactsOnTopScript): void {
    $this->actingAs($this->captain);

    visit(route('admin.interclubs.captain-selection'))
        ->on()->iPhone15()
        ->click('[data-row-menu-primary]')
        ->wait(1)
        ->assertPresent('[data-roster-contact]')
        ->assertScript($contactsOnTopScript, 'ok');
});
