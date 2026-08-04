<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;

/*
 * The secondary actions of a row must be reachable with a thumb. A dropdown
 * anchored to a button at the right edge of a 375px screen has nowhere to go,
 * so below lg the same panel is laid out as a bottom sheet.
 */
beforeEach(function (): void {
    Club::factory()->ownClub()->create();

    $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
    $subscription->payments()->create([
        'reference' => '100/2505/00120',
        'amount_due' => 125,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    $this->treasurer = User::factory()->withRole(Role::TREASURY)->create();
});

it('opens the secondary actions inside the screen on a phone', function (): void {
    $this->actingAs($this->treasurer);

    $page = visit(route('admin.treasury.payments'))->resize(375, 812);

    $page->click('[data-row-menu-trigger]');

    $geometry = $page->script(<<<'JS'
        (async () => {
          const panel = document.querySelector('[data-row-menu-panel]');
          if (!panel) return { found: false };
          // Alpine toggles on its own tick; wait for the panel rather than assume.
          for (let i = 0; i < 40 && getComputedStyle(panel).display === 'none'; i++) {
            await new Promise((r) => setTimeout(r, 50));
          }
          const r = panel.getBoundingClientRect();
          const item = panel.querySelector('a, button');
          return {
            found: true,
            visible: getComputedStyle(panel).display !== 'none',
            insideRight: Math.round(r.right) <= 376,
            insideLeft: Math.round(r.left) >= -1,
            insideBottom: Math.round(r.bottom) <= 813,
            itemHeight: item ? Math.round(item.getBoundingClientRect().height) : 0,
            display: getComputedStyle(panel).display,
          };
        })()
    JS);

    $g = $geometry[0] ?? $geometry;

    expect($g['found'])->toBeTrue('the row menu should render');
    expect($g['visible'])->toBeTrue('tapping the trigger should reveal it');
    expect($g['insideRight'])->toBeTrue('the panel must not run off the right edge');
    expect($g['insideLeft'])->toBeTrue('the panel must not run off the left edge');
    expect($g['insideBottom'])->toBeTrue('the panel must not run below the fold');
    expect($g['itemHeight'])->toBeGreaterThanOrEqual(44, 'a thumb needs a 44px target');
});
