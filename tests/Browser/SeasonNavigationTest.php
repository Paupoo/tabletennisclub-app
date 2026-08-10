<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Season;

/*
 * DS-A: a criterion that determines what the page is about — exactly one value,
 * never empty — is navigation, and navigation is visible without opening
 * anything. Presence in the DOM is not visibility: the season used to live in a
 * closed drawer, which renders it and hides it off-screen, while the page told
 * the reader to "select a season". So this is measured in the browser, on the
 * painted box, not on the markup.
 */
const SEASON_CONTROL = <<<'JS_WRAP'
(() => {
  const controls = [...document.querySelectorAll('[wire\\:model], [wire\\:model\\.live], select, input')]
    .filter((el) => (el.getAttribute('wire:model.live') || el.getAttribute('wire:model') || '').toLowerCase().includes('seasonid'));

  const visible = controls.filter((el) => {
    const box = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return box.width > 0 && box.height > 0 && box.left >= 0 && box.left < window.innerWidth
      && cs.visibility !== 'hidden' && cs.display !== 'none';
  });

  return JSON.stringify({ inDom: controls.length, visible: visible.length });
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    Season::factory()->create(['is_active' => true, 'name' => '2026-2027']);

    $this->actingAs($this->admin);
});

it('shows the season on the schedule without opening a drawer', function (string $route): void {
    $reading = json_decode((string) visit(route($route))->resize(1440, 900)->script(SEASON_CONTROL), true);

    expect($reading['inDom'])->toBeGreaterThan(0, 'no season control rendered at all on ' . $route)
        ->and($reading['visible'])->toBeGreaterThan(0, 'the season is rendered but nothing on screen shows it on ' . $route);
})->with([
    'interclub schedule' => ['admin.interclubs.interclubs'],
    'training packs' => ['admin.trainings.index'],
]);
