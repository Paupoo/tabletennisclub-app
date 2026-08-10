<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Database\Seeders\InterclubResultsSeeder;
use Database\Seeders\InterclubScheduleSeeder;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::PRESIDENT,
    ]);
});

/*
 * WCAG 1.4.3 asks for 4.5:1 on body text. The palette reaches its low steps
 * through alpha compositing — text-base-content/40 and friends — which no
 * static check can judge, because the ratio depends on what the text sits on.
 * So we measure it in the browser, on the composited result.
 *
 * Icons and borders are deliberately out of scope: they answer to 1.4.11 at
 * 3:1, and folding them in here would hide real text failures behind noise.
 *
 * `__SCOPE__` is a CSS selector the caller substitutes to narrow the sweep.
 * It exists for the public pages: their hero and section headers sit on
 * photography, whose luminance no `backgroundColor` walk can read, so an
 * unscoped sweep there reports white-on-white failures that nobody can see.
 * Scoping the probe to the surface under test keeps the result honest.
 */
$contrastProbe = <<<'JS'
(() => {
  // Tailwind v4 emits oklch(), so getComputedStyle hands back colours in a space
  // that cannot be read as RGB triplets. Let the canvas do the conversion: paint
  // the colour, read the pixel back, and the maths below stays format-agnostic.
  const cv = document.createElement('canvas');
  cv.width = cv.height = 1;
  const ctx = cv.getContext('2d', { willReadFrequently: true });
  const cache = new Map();
  const toRgba = (css) => {
    if (cache.has(css)) return cache.get(css);
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = '#000';
    ctx.fillStyle = css;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    const out = [d[0], d[1], d[2], d[3] / 255];
    cache.set(css, out);
    return out;
  };

  const lum = ([r, g, b]) => {
    const [R, G, B] = [r, g, b].map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * R + 0.7152 * G + 0.0722 * B;
  };

  // Composite every translucent background down to the first opaque ancestor.
  const backdrop = (el) => {
    const stack = [];
    for (let n = el; n; n = n.parentElement) {
      const [r, g, b, a] = toRgba(getComputedStyle(n).backgroundColor);
      if (a === 0) continue;
      stack.push([r, g, b, a]);
      if (a > 0.99) break;
    }
    let base = [255, 255, 255];
    for (let i = stack.length - 1; i >= 0; i--) {
      const [r, g, b, a] = stack[i];
      base = [r, g, b].map((v, k) => v * a + base[k] * (1 - a));
    }
    return base;
  };

  // A scope that matches nothing would make the assertion pass without probing
  // anything at all, so say so rather than report success.
  const roots = document.querySelectorAll('__SCOPE__');
  if (roots.length === 0) return ['no element matched the probe scope "__SCOPE__"'];

  const targets = new Set();
  for (const root of roots) {
    for (const el of root.querySelectorAll('p, span, div, td, th, li, label, small, a, button')) targets.add(el);
  }

  const failures = [];
  for (const el of targets) {
    const text = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent.trim()).join('');
    if (text.length < 3) continue;
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) continue;

    const bg = backdrop(el);
    const [fr, fg_, fb, fa] = toRgba(cs.color);
    const mixed = [fr, fg_, fb].map((v, i) => v * fa + bg[i] * (1 - fa));

    const l1 = lum(mixed), l2 = lum(bg);
    const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);

    const px = parseFloat(cs.fontSize);
    const bold = parseInt(cs.fontWeight, 10) >= 700;
    const required = (px >= 24 || (bold && px >= 18.66)) ? 3 : 4.5;

    if (ratio < required) {
      failures.push(ratio.toFixed(2) + ':1 (needs ' + required + ') - "' + text.slice(0, 34) + '" [' + cs.fontSize + ' ' + cs.color + ']');
    }
  }
  return failures.slice(0, 25);
})()
JS;

/** Compiles the probe for one surface; the default sweeps the whole document. */
$probe = fn (string $scope = ':root'): string => str_replace('__SCOPE__', $scope, $contrastProbe);

it('keeps body text above the AA contrast threshold on the members list', function () use ($probe): void {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script($probe());

    // script() hands back one entry per script; older shapes return the value flat.
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold:\n%s",
        implode("\n", $failures),
    ));
});

/*
 * The members list was the first slice. These are the next densest screens, and
 * the ones where a treasurer or a secretary reads small figures for a long time.
 */
it('keeps body text above the AA threshold on the dense back-office screens', function (string $route, Role $role) use ($probe): void {
    $this->actingAs(User::factory()->withRole($role)->create());

    $page = visit(route($route));

    $result = $page->script($probe());
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold on %s:\n%s",
        $route,
        implode("\n", $failures),
    ));
})->with([
    ['admin.treasury.payments', Role::TREASURY],
    ['admin.treasury.transactions', Role::TREASURY],
    ['admin.users.delegations', Role::MEMBERS],
    ['admin.website.articles.index', Role::WEBSITE],
]);

/*
 * The interclubs domain carries the densest tables in the application, and its
 * figures — weeks, team counts, scores — are read quickly, often in a badly lit
 * sports hall. Fixtures are seeded first: an empty page has no figures to dim.
 */
it('keeps body text above the AA threshold on the interclubs screens', function (string $route) use ($probe): void {
    Club::firstOrCreate(
        ['licence' => 'BBW214'],
        ['name' => 'C.T.T Ottignies-Blocry', 'is_own_club' => true, 'city_code' => '1340', 'city_name' => 'Ottignies'],
    );
    $this->seed(InterclubScheduleSeeder::class);
    $this->seed(InterclubResultsSeeder::class);

    $user = User::factory()->withRole(Role::INTERCLUBS)->create();

    // The selections screen answers to access-selections, which a captaincy
    // satisfies on its own — that is the whole point of the Gate.
    Team::query()
        ->whereHas('club', fn ($q) => $q->where('is_own_club', true))
        ->first()
        ?->update(['captain_id' => $user->id]);

    $this->actingAs($user);

    $page = visit(route($route));

    $result = $page->script($probe());
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold on %s:\n%s",
        $route,
        implode("\n", $failures),
    ));
})->with([
    'admin.interclubs.results',
    'admin.interclubs.clubs',
    'admin.interclubs.captain-selection',
]);

/*
 * The selection drawer holds the densest figures of the whole back office —
 * ranking chip, availability, played/selected counters, contact details — and
 * none of it is measured by the page-load probes above, because the drawer
 * starts closed. It carried the worst pairing in the application: 7px labels at
 * 30% opacity, 1.96:1.
 */
it('keeps the selection drawer above the AA threshold once it is open', function () use ($probe): void {
    $season = Season::factory()->create([
        'is_active' => true,
        'start_at' => now()->subMonths(4),
        'end_at' => now()->addMonths(6),
    ]);
    $ownClub = Club::factory()->ownClub()->create();
    $league = League::factory()->create([
        'season_id' => $season->id,
        'category' => 'MEN',
    ]);

    $captain = User::factory()->isCompetitor()->create();

    $team = Team::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'club_id' => $ownClub->id,
        'captain_id' => $captain->id,
    ]);

    // Contact details, a note and mixed availabilities: every dense row the
    // drawer can render has to be on screen for the probe to mean anything.
    $players = User::factory()->isCompetitor()->count(4)->create(['phone_number' => '0470 12 34 56']);
    $team->users()->attach($players->pluck('id'));

    $fixture = Interclub::factory()->create([
        'season_id' => $season->id,
        'league_id' => $league->id,
        'visited_team_id' => $team->id,
        'week_number' => 3,
        'total_players' => 4,
        'start_date_time' => now()->addDays(6)->setTime(19, 45),
    ]);

    foreach ($players as $i => $p) {
        $fixture->users()->attach($p->id, [
            'availability' => ['available', 'maybe', 'unavailable', 'available'][$i],
            'is_selected' => $i === 0,
            'availability_note' => $i === 1 ? 'Je dois partir à 22h' : null,
        ]);
    }

    $this->actingAs($captain);

    $page = visit(route('admin.interclubs.captain-selection'))
        ->click('[data-match-row]:first-of-type [data-row-menu-primary]')
        ->assertSee(__('Team roster'));

    $result = $page->script($probe());
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold in the selection drawer:\n%s",
        implode("\n", $failures),
    ));
});

/*
 * Nothing measured the public site until now, and it is the half of the
 * application a visitor sees first. Its dark surfaces are the exposed ones: the
 * footer sits on gray-900 and the sponsor tiles on gray-800, while the greys the
 * markup asks for are clamped towards a colour computed for a light ground.
 *
 * The footer is on every public page, so both routes probe it; the sponsor tiles
 * only render on the home page, and only once a sponsor exists.
 */
it('keeps text readable on the dark surfaces of the public site', function (string $route, string $surface) use ($probe): void {
    Club::factory()->ownClub()->create();

    $page = visit(route($route));

    $result = $page->script($probe($surface));
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold on %s, inside %s:\n%s",
        $route,
        $surface,
        implode("\n", $failures),
    ));
})->with([
    ['home', 'footer'],
    ['results', 'footer'],
    ['home', '[data-sponsor-tile]'],
]);
