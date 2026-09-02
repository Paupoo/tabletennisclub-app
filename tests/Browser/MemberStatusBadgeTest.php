<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/*
 * The members list is the most visited screen of the back office, and its row
 * actions cell used to carry two status badges next to the Edit control. A cell
 * sized for controls is not sized for prose: "Compte créé" folded onto two lines
 * on every row and came to rest against the payment badge.
 *
 * The box is NOT the measure that catches it: a badge-xs has a fixed height, so
 * the second line never makes it taller — it is clipped. Measured before the
 * fix: "Compte créé" reported a 16px box for 22px of content, while "Impayé"
 * needed 15px and fitted. Overflow is what to assert on.
 */
$badgeProbe = <<<'JS'
(() => {
  const rows = [...document.querySelectorAll('table tbody tr')];
  const out = [];
  for (const row of rows) {
    for (const badge of row.querySelectorAll('.badge')) {
      const r = badge.getBoundingClientRect();
      if (r.width < 1 || r.height < 1) continue;
      if (badge.scrollHeight > badge.clientHeight + 1) {
        out.push(badge.textContent.trim() + ' -> ' + badge.scrollHeight + 'px of text in a ' + badge.clientHeight + 'px badge');
      }
    }
  }
  return out.slice(0, 10);
})()
JS;

it('keeps every status badge of the members table on one line', function () use ($badgeProbe): void {
    $this->actingAs(User::factory()->withRole(Role::MEMBERS)->create());
    User::factory()->count(4)->create();

    $page = visit(route('admin.users.index'));
    $page->resize(1440, 900);

    $result = $page->script($badgeProbe);
    $wrapped = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($wrapped)->toBe([], sprintf(
        "Status badges wrapping in the members table:\n%s",
        implode("\n", $wrapped),
    ));
});
