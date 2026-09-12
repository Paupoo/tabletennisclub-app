<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\ClubPosts\Models\NewsPost;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Interclub\Models\Interclub;
use App\Domains\Competitions\Interclub\Models\League;
use App\Domains\Competitions\Interclub\Models\Season;
use App\Domains\Competitions\Interclub\Models\Team;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\NewsPostStatusEnum;
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

  // `opacity` never reaches getComputedStyle().color: it composites the whole
  // element at paint time, after the cascade has resolved. A probe that reads
  // `color` alone is blind to it by construction — and opacity-* is how this
  // codebase dims most of its text. Fold the whole ancestor chain into the
  // alpha, which is exactly what the compositor does.
  const chainOpacity = (el) => {
    let o = 1;
    for (let n = el; n && n !== document.documentElement; n = n.parentElement) {
      o *= parseFloat(getComputedStyle(n).opacity || '1');
    }
    return o;
  };

  // A scope that matches nothing would make the assertion pass without probing
  // anything at all, so say so rather than report success.
  const roots = document.querySelectorAll('__SCOPE__');
  if (roots.length === 0) return ['no element matched the probe scope "__SCOPE__"'];

  const targets = new Set();
  for (const root of roots) {
    // strong/b/em/code carry the words a reader is meant to notice, and prose
    // drives them from their own theme variables — the article body failed on
    // exactly those while the paragraph around them measured fine.
    for (const el of root.querySelectorAll('p, span, div, td, th, li, label, small, a, button, strong, b, em, i, code, dt, dd')) targets.add(el);
  }

  const failures = [];
  for (const el of targets) {
    const text = [...el.childNodes].filter((n) => n.nodeType === 3).map((n) => n.textContent.trim()).join('');
    if (text.length < 3) continue;
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') continue;
    // Fully transparent is hidden, not unreadable: x-show leaves panels at 0.
    if (chainOpacity(el) < 0.01) continue;
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) continue;

    const bg = backdrop(el);
    const [fr, fg_, fb, fa] = toRgba(cs.color);
    const alpha = fa * chainOpacity(el);
    const mixed = [fr, fg_, fb].map((v, i) => v * alpha + bg[i] * (1 - alpha));

    const l1 = lum(mixed), l2 = lum(bg);
    const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);

    const px = parseFloat(cs.fontSize);
    const bold = parseInt(cs.fontWeight, 10) >= 700;
    const required = (px >= 24 || (bold && px >= 18.66)) ? 3 : 4.5;

    if (ratio < required) {
      failures.push(ratio.toFixed(2) + ':1 (needs ' + required + ') - "' + text.slice(0, 34) + '" [' + cs.fontSize + ' ' + cs.color + ' a=' + alpha.toFixed(2) + ']');
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
        ->assertSee(__('Team roster'))
        // The drawer fades in. Now that the probe reads opacity, measuring it
        // mid-transition reports the animation rather than the design.
        ->wait(1);

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

/*
 * Everything above measures the light theme, because until now that was the only
 * theme the application was ever asked about: `inDarkMode` appeared nowhere in
 * the suite, which is how a page could serve 1.02:1 with a green run.
 *
 * The dark theme is not a variant of the light one here — the greys the markup
 * asks for are clamped towards a colour computed from `base-content`, so they
 * MOVE when the theme flips, while any hard-coded surface underneath them does
 * not. That is a different failure mode, and it needs its own sweep.
 *
 * The authentication screens are deliberately absent: `layouts/login` paints its
 * page with a gradient, and a gradient has no `backgroundColor` for the probe to
 * walk, so it would fall back to assuming white and report failures nobody can
 * see. Their dark theme is guarded by DarkModeSurfaceTest instead, which reads
 * solid fills only and is immune to that blind spot.
 */
it('keeps text readable on the dark surfaces of the public site in dark mode', function (string $route, string $surface) use ($probe): void {
    Club::factory()->ownClub()->create();

    $page = visit(route($route))->inDarkMode()->wait(1);

    $result = $page->script($probe($surface));
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold on %s in dark mode, inside %s:\n%s",
        $route,
        $surface,
        implode("\n", $failures),
    ));
})->with([
    ['home', 'footer'],
    ['results', 'footer'],
    ['home', '[data-sponsor-tile]'],
]);

/*
 * The back office already answers to the dark theme, and the next lot rewrites
 * three of its global clamps — `.text-error`, `.badge-soft` and the dark value
 * of `--color-base-300`, which today is darker than the card it borders. These
 * two screens carry the densest badges and the most inline error text in the
 * application, so they are where a mistake in those clamps would surface first.
 */
it('keeps body text above the AA threshold on the dense back-office screens in dark mode', function (string $route, Role $role) use ($probe): void {
    $this->actingAs(User::factory()->withRole($role)->create());

    $page = visit(route($route))->inDarkMode()->wait(1);

    $result = $page->script($probe());
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold on %s in dark mode:\n%s",
        $route,
        implode("\n", $failures),
    ));
})->with([
    ['admin.treasury.payments', Role::TREASURY],
    ['admin.users.delegations', Role::MEMBERS],
]);

/*
 * The article editor shows a live preview of what will be published, and it
 * drew that preview on bg-white with text-gray-800 — a sheet of paper on a
 * dark page, and a preview that no longer matched the article. It now carries
 * the same `prose-*` settings as the public page, so the two agree in both
 * themes. The Markdown help panel beside it was a light blue card with
 * text-gray-700 on it.
 */
it('keeps the article editor readable in dark mode', function () use ($probe): void {
    $article = NewsPost::factory()->create([
        'status' => NewsPostStatusEnum::PUBLISHED,
        'content' => "## Titre\n\nUn paragraphe avec du **gras** et un [lien](https://example.test).\n\n- point 1\n- point 2",
    ]);

    $this->actingAs(User::factory()->withRole(Role::WEBSITE)->create());

    $page = visit(route('admin.website.articles.edit', $article))->inDarkMode()->wait(1);

    $result = $page->script($probe());
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold in the article editor:\n%s",
        implode("\n", $failures),
    ));
});

/*
 * The article body is where the dark theme did its worst damage, and where no
 * assertion reached. `articles/show.blade.php` pins paragraphs, headings and
 * list items through `prose-*` overrides, but says nothing about `strong`, `td`
 * or `th` — daisyUI drives those from `--tw-prose-bold`, which follows the
 * theme. Half the body followed the theme and half did not, so a convocation's
 * date and venue — carried by the `<strong>` precisely because they matter —
 * measured 1.12:1 while the paragraph around them read fine.
 *
 * The content below is not decorative: it reproduces that exact shape, a table
 * and emphasised text inside a paragraph, because a fixture without them proves
 * nothing about the pairing that failed.
 */
it('keeps the article body readable in both themes', function (string $theme) use ($probe): void {
    Club::factory()->ownClub()->create();

    $article = NewsPost::factory()->create([
        'slug' => 'convocation-assemblee-generale',
        'status' => NewsPostStatusEnum::PUBLISHED,
        'content' => <<<'HTML'
            <p>Les membres sont convoqués à l'<strong>assemblée générale de fin de saison</strong>.</p>
            <table>
              <thead><tr><th>Date</th><th>Lieu</th></tr></thead>
              <tbody><tr><td>Jeudi 12 juin</td><td>Centre sportif J. Demeester</td></tr></tbody>
            </table>
            <ul><li>Rapport moral</li><li>Élection du comité</li></ul>
            <blockquote>La présence de chaque membre compte.</blockquote>
            HTML,
    ]);

    $page = visit(route('public.clubPosts.show', $article->slug));
    $page = $theme === 'dark' ? $page->inDarkMode() : $page->inLightMode();
    $page->wait(1);

    $result = $page->script($probe('.prose'));
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold in the article body (%s theme):\n%s",
        $theme,
        implode("\n", $failures),
    ));
})->with(['light', 'dark']);

/*
 * The interclubs screens were written entirely in the light half of Tailwind's
 * neutral scale — `text-gray-900` for every team name, opponent and score. The
 * global clamp in app.css catches `text-gray-300/400/500` and routes them to a
 * theme-aware token, so the *supporting* text followed the dark theme while the
 * emphasis text stayed dark grey on a dark ground: the team detail page rendered
 * its player names, divisions and opponents at around 1.3:1, and the results
 * screen its team headings. The inversion is what makes it easy to miss — the
 * addresses and dates read perfectly on the same screenshot.
 *
 * The same views also painted their own surfaces in the light palette: the
 * edit screen drew its empty checkboxes in bg-white and its hovered row in
 * bg-gray-50, so a white square sat on every line and the row under the cursor
 * turned into a white band.
 *
 * The two screens that take a team are given one: a route without it renders
 * nothing to measure.
 */
it('keeps the interclubs screens above the AA threshold in dark mode', function (string $key) use ($probe): void {
    Club::firstOrCreate(
        ['licence' => 'BBW214'],
        ['name' => 'C.T.T Ottignies-Blocry', 'is_own_club' => true, 'city_code' => '1340', 'city_name' => 'Ottignies'],
    );
    $this->seed(InterclubScheduleSeeder::class);
    $this->seed(InterclubResultsSeeder::class);

    $this->actingAs(User::factory()->withRole(Role::INTERCLUBS)->create());

    $team = Team::query()
        ->whereHas('club', fn ($q) => $q->where('is_own_club', true))
        ->firstOrFail();

    $url = in_array($key, ['admin.interclubs.teams.show', 'admin.interclubs.teams.edit'], true)
        ? route($key, $team->id)
        : route($key);

    $page = visit($url)->inDarkMode()->wait(1);

    $result = $page->script($probe());
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold on %s in dark mode:\n%s",
        $key,
        implode("\n", $failures),
    ));
})->with([
    'admin.interclubs.teams',
    'admin.interclubs.teams.show',
    'admin.interclubs.teams.edit',
    'admin.interclubs.results',
    'admin.interclubs.division-setup',
    'admin.interclubs.teams.builder',
]);

/*
 * `--color-warning-content` is the foreground that reads *on* the warning fill,
 * and the dark theme redefines it to the warning colour itself — which paints
 * every solid warning yellow on yellow, 1.00:1. The banner below is the one a
 * secretary meets first: it lists what the club still has to provide before
 * members can ask for an attestation, and it was unreadable in dark mode. The
 * screen is left in its default state on purpose, because that is the only
 * state in which the banner renders at all.
 */
it('keeps the warning banner readable in dark mode', function () use ($probe): void {
    Club::factory()->ownClub()->create();

    $this->actingAs(User::factory()->withRole(Role::ATTESTATIONS)->create());

    $page = visit(route('admin.attestations.index'))->inDarkMode()->wait(1);

    $page->assertSee(__('the club seal'));

    $result = $page->script($probe('.alert-warning'));
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold in the warning banner:\n%s",
        implode("\n", $failures),
    ));
});
