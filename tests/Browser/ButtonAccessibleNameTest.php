<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use Database\Seeders\InterclubResultsSeeder;
use Database\Seeders\InterclubScheduleSeeder;

/*
 * Row actions are drawn as bare icons with a tooltip. Mary renders that tooltip
 * as a CSS data-tip attribute, which no screen reader announces, so the control
 * is read as "button" and nothing else — including the one that deletes a club.
 *
 * A row of identical, unnamed buttons where one is destructive is the worst
 * possible place for an anonymous control, so this is measured on the rendered
 * page rather than trusted to the markup.
 */
$unnamedButtons = <<<'JS'
(() => {
  const named = (el) => {
    if ((el.textContent || '').trim().length) return true;
    if (el.getAttribute('aria-label')) return true;
    const by = el.getAttribute('aria-labelledby');
    if (by && by.split(/\s+/).some((id) => document.getElementById(id))) return true;
    return !!el.getAttribute('title');
  };

  const out = [];
  const seen = new Set();
  for (const el of document.querySelectorAll('button, [role=button]')) {
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') continue;
    if (el.getBoundingClientRect().width < 1) continue;
    if (named(el)) continue;
    const key = (el.className || '').toString().slice(0, 60) + '|' + (el.getAttribute('wire:click') || el.getAttribute('@click') || '');
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(key);
  }
  return out.slice(0, 15);
})()
JS;

it('names every button on the interclubs screens', function (string $route) use ($unnamedButtons): void {
    // Without fixtures the page renders no rows, and row actions are exactly what
    // this measures — an empty page would pass while announcing nothing.
    Club::firstOrCreate(
        ['licence' => 'BBW214'],
        ['name' => 'C.T.T Ottignies-Blocry', 'is_own_club' => true, 'city_code' => '1340', 'city_name' => 'Ottignies'],
    );

    $this->seed(InterclubScheduleSeeder::class);
    $this->seed(InterclubResultsSeeder::class);

    $this->actingAs(User::factory()->withRole(Role::INTERCLUBS)->create());

    $page = visit(route($route));

    $result = $page->script($unnamedButtons);
    $unnamed = is_array($result[0] ?? null) ? $result[0] : (array) $result;

    expect($unnamed)->toBe([], sprintf(
        "Buttons a screen reader announces as \"button\" and nothing else on %s:\n%s",
        $route,
        implode("\n", $unnamed),
    ));
})->with([
    'admin.interclubs.clubs',
    'admin.interclubs.results',
]);
