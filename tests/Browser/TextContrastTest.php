<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;

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

  const failures = [];
  for (const el of document.querySelectorAll('p, span, div, td, th, li, label, small, a, button')) {
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

it('keeps body text above the AA contrast threshold on the members list', function () use ($contrastProbe): void {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'));

    $result = $page->script($contrastProbe);

    // script() hands back one entry per script; older shapes return the value flat.
    $failures = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($failures)->toBe([], sprintf(
        "Text below the WCAG 1.4.3 threshold:\n%s",
        implode("\n", $failures),
    ));
});
