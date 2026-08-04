<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;

/*
 * WCAG 2.5.8 sets 24x24 CSS pixels as the floor for a pointer target, and the
 * Apple HIG asks 44x44 for anything used one-handed. The members list is the
 * screen a secretary works from on a phone, so it is where this is measured.
 *
 * The check is on the rendered box, not on a class name: padding, line-height
 * and icon size all decide the real target, and only the browser knows the sum.
 */
$tapProbe = <<<'JS'
(() => {
  const sel = 'a[href], button, input:not([type=hidden]), select, textarea, [role=button], summary';
  const seen = new Set();
  const small = [];
  for (const el of document.querySelectorAll(sel)) {
    const cs = getComputedStyle(el);
    if (cs.visibility === 'hidden' || cs.display === 'none' || cs.opacity === '0') continue;
    const r = el.getBoundingClientRect();
    if (r.width < 1 || r.height < 1) continue;
    const w = Math.round(r.width), h = Math.round(r.height);
    if (Math.min(w, h) >= 24) continue;

    const label = (el.getAttribute('aria-label') || el.textContent || el.type || el.tagName).trim().slice(0, 30);
    const key = label + '|' + w + 'x' + h;
    if (seen.has(key)) continue;
    seen.add(key);
    small.push(w + 'x' + h + ' - "' + label + '"');
  }
  return small.slice(0, 20);
})()
JS;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::PRESIDENT,
    ]);
});

it('keeps every tap target reachable with a thumb on the members list', function () use ($tapProbe): void {
    User::factory()->count(3)->create();

    $this->actingAs($this->admin);

    $page = visit(route('admin.users.index'))->resize(375, 812);

    $result = $page->script($tapProbe);
    $small = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($small)->toBe([], sprintf(
        "Targets under the 24x24 floor of WCAG 2.5.8:\n%s",
        implode("\n", $small),
    ));
});

/*
 * The treasury screens carry the densest rows in the back office, and they are
 * worked from a phone as often as from a desk.
 */
it('keeps every tap target reachable on the treasury screens', function (string $route, Role $role) use ($tapProbe): void {
    $this->actingAs(User::factory()->withRole($role)->create());

    $page = visit(route($route))->resize(375, 812);

    $result = $page->script($tapProbe);
    $small = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($small)->toBe([], sprintf(
        "Targets under the 24x24 floor of WCAG 2.5.8 on %s:\n%s",
        $route,
        implode("\n", $small),
    ));
})->with([
    ['admin.treasury.payments', Role::TREASURY],
    ['admin.treasury.transactions', Role::TREASURY],
    ['admin.treasury.fines', Role::FINES],
]);
