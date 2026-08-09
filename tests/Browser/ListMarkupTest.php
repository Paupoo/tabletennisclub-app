<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;

/*
 * A screen reader announces "list, N items" by counting <li> children. A <ul>
 * holding anything else reports the wrong count and its arrow-key navigation
 * turns erratic — in the main menu, which everyone uses.
 *
 * The breadcrumb trail is deliberately out of scope: Mary renders it as a <ul>
 * of <span> separators, inside the package, and working around it would mean
 * patching vendor markup. It is a declared limitation, not an oversight.
 */
$invalidLists = <<<'JS'
(() => {
  const out = [];
  for (const list of document.querySelectorAll('ul, ol')) {
    if (list.closest('.breadcrumb-trail')) continue;
    const wrong = [...list.children].filter((c) => !['LI', 'SCRIPT', 'TEMPLATE'].includes(c.tagName));
    if (!wrong.length) continue;
    out.push(
      '<' + list.tagName.toLowerCase() + ' class="' + (list.className || '').toString().slice(0, 40) + '"> contains ' +
      wrong.slice(0, 3).map((c) => '<' + c.tagName.toLowerCase() + '>').join(', ')
    );
  }
  return [...new Set(out)].slice(0, 10);
})()
JS;

it('builds every list out of list items', function (string $route) use ($invalidLists): void {
    $this->actingAs(User::factory()->withRole(Role::INTERCLUBS)->create());

    $page = visit(route($route));

    $result = $page->script($invalidLists);
    $invalid = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($invalid)->toBe([], sprintf(
        "Lists a screen reader will miscount on %s:\n%s",
        $route,
        implode("\n", $invalid),
    ));
})->with([
    'dashboard',
    'admin.interclubs.clubs',
]);
