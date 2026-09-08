<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;

/*
 * A white page is perfectly contrasted. WCAG has nothing to say about it, and
 * TextContrastTest never will: every pairing on it passes. Yet a visitor whose
 * system asks for dark mode and gets a full-screen white slab has been served
 * the wrong page — and that is the failure this suite could not see.
 *
 * So this file measures a different thing: how much of the screen is painted
 * light while the document declares itself dark. The rule is the one the design
 * system already states for night reading — no surface covering more than a
 * quarter of the viewport may exceed 0.25 relative luminance, which sits around
 * #8a8a8a. A dark card at #222 measures 0.017; white measures 1.0. The threshold
 * is deliberately generous: it catches slabs, not shades.
 *
 * Only an element's OWN background counts. Inherited paint belongs to whichever
 * ancestor declared it, and that ancestor is measured on its own terms — folding
 * it in would report the same slab once per descendant.
 */
$slabProbe = <<<'JS'
(() => {
  // Tailwind v4 emits oklch(); the canvas is the only reliable converter.
  const cv = document.createElement('canvas');
  cv.width = cv.height = 1;
  const ctx = cv.getContext('2d', { willReadFrequently: true });
  const toRgba = (css) => {
    ctx.clearRect(0, 0, 1, 1);
    ctx.fillStyle = '#000';
    ctx.fillStyle = css;
    ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    return [d[0], d[1], d[2], d[3] / 255];
  };

  const lum = ([r, g, b]) => {
    const [R, G, B] = [r, g, b].map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
    });
    return 0.2126 * R + 0.7152 * G + 0.0722 * B;
  };

  const theme = document.documentElement.getAttribute('data-theme');
  // The theme lands from the bundle, after first paint. If it has not arrived,
  // the measurement below would judge the pre-boot frame rather than the page.
  if (theme !== 'dark') return ['the document never reached data-theme="dark" (got: ' + theme + ')'];

  const vw = window.innerWidth, vh = window.innerHeight;
  const screenArea = vw * vh;
  const LUMINANCE_CEILING = 0.25;
  const AREA_FLOOR = 0.25;

  const offenders = [];
  const seen = new Set();

  for (const el of [document.documentElement, document.body, ...document.body.querySelectorAll('*')]) {
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none') continue;

    const [r, g, b, a] = toRgba(cs.backgroundColor);
    // Translucent paint is judged with whatever shows through it; only solid
    // fills are unambiguous, and only they can be a slab on their own.
    if (a < 0.9) continue;

    const l = lum([r, g, b]);
    if (l <= LUMINANCE_CEILING) continue;

    const rect = el.getBoundingClientRect();
    // An element taller than the screen still only covers one screen at a time.
    const area = Math.min(rect.width, vw) * Math.min(rect.height, vh);
    if (area / screenArea < AREA_FLOOR) continue;

    const key = cs.backgroundColor + '|' + Math.round(area);
    if (seen.has(key)) continue;
    seen.add(key);

    const where = el.tagName.toLowerCase()
      + (el.id ? '#' + el.id : '')
      + (typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '');

    offenders.push(
      Math.round((area / screenArea) * 100) + '% of the screen at luminance '
      + l.toFixed(2) + ' (' + cs.backgroundColor + ') - ' + where
    );
  }

  return offenders.slice(0, 10);
})()
JS;

/** Reads the probe's return value, whatever shape script() hands back. */
function slabFailures(mixed $result): array
{
    return is_array($result[0] ?? null) ? $result[0] : (array) $result;
}

/*
 * The screens below already answer to the dark theme today — the authentication
 * tunnel, the signed meeting pages and the error pages were built with theme
 * tokens throughout. They are the half that works, and this test is what keeps
 * them that way while the public site is migrated onto the same vocabulary.
 */
it('paints no light slab in dark mode on the authentication screens', function (string $route) use ($slabProbe): void {
    $page = visit(route($route))->inDarkMode()->wait(1);

    $offenders = slabFailures($page->script($slabProbe));

    expect($offenders)->toBe([], sprintf(
        "Light surfaces served in dark mode on %s:\n%s",
        $route,
        implode("\n", $offenders),
    ));
})->with([
    'login',
    'password.request',
]);

it('paints no light slab in dark mode on a dense back-office screen', function () use ($slabProbe): void {
    $this->actingAs(User::factory()->withRole(Role::TREASURY)->create());

    $page = visit(route('admin.treasury.payments'))->inDarkMode()->wait(1);

    $offenders = slabFailures($page->script($slabProbe));

    expect($offenders)->toBe([], sprintf(
        "Light surfaces served in dark mode on the payments screen:\n%s",
        implode("\n", $offenders),
    ));
});

/*
 * And here is the grievance, stated as an assertion. Every one of these fails
 * today: the public layout hard-codes `bg-white text-gray-900` on <body>, so the
 * page stays white while the document says dark. They are the acceptance test
 * for the migration, not a report on it — enable them with the lot that lands
 * the fix, one route at a time if that helps the review.
 */
it('paints no light slab in dark mode on the public site', function (string $route) use ($slabProbe): void {
    Club::factory()->ownClub()->create();

    $page = visit(route($route))->inDarkMode()->wait(1);

    $offenders = slabFailures($page->script($slabProbe));

    expect($offenders)->toBe([], sprintf(
        "Light surfaces served in dark mode on %s:\n%s",
        $route,
        implode("\n", $offenders),
    ));
})->with([
    'home',
    'results',
    'eventPosts',
    'public.clubPosts.index',
])->skip('Acceptance test for the public dark mode migration — enable it with the lot that tokenises the guest layout.');
