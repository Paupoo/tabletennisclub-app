<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;

/*
 * The back office draws its panels two ways: Mary's <x-card>, which daisyUI
 * renders borderless with 8px corners, and a hand-rolled
 * "rounded-xl border border-base-200" div, bordered with 12px corners. Twenty-two
 * screens render both at once, so the same object has two shapes on one page.
 *
 * The design system asks for one card: a 1px grey border and 12px corners. That
 * is a property of the painted box, not of the markup, so it is measured here
 * rather than asserted on the HTML.
 */
const CARD_SURFACE = <<<'JS_WRAP'
(() => {
  const px = (v) => Math.round(parseFloat(v) || 0);

  // Off-screen chrome (a closed drawer, a dialog) is not what the reader sees.
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    const cs = getComputedStyle(el);
    return r.width > 40 && r.height > 20 && cs.visibility !== 'hidden' && cs.display !== 'none';
  };

  const cards = [...document.querySelectorAll('.card')]
    .filter((el) => !el.closest('.drawer-side, dialog'))
    .filter(visible)
    // border-none is the documented opt-out; it must keep working.
    .filter((el) => getComputedStyle(el).borderTopStyle !== 'none');

  return JSON.stringify({
    total: cards.length,
    borderless: cards.filter((el) => px(getComputedStyle(el).borderTopWidth) < 1).length,
    radii: [...new Set(cards.map((el) => px(getComputedStyle(el).borderTopLeftRadius)))].sort((a, b) => a - b),
  });
})()
JS_WRAP;

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->isCommitteeMember()->create([
        'committee_role' => CommitteeRolesEnum::PRESIDENT,
    ]);

    $this->actingAs($this->admin);
});

it('draws every admin card with the border the design system asks for', function (string $route): void {
    User::factory()->count(3)->create();

    $surface = json_decode((string) visit(route($route))->resize(1440, 900)->script(CARD_SURFACE), true);

    expect($surface['total'])->toBeGreaterThan(0, "no card rendered on {$route}, the probe measured nothing");
    expect($surface['borderless'])->toBe(0, "{$surface['borderless']} of {$surface['total']} cards on {$route} carry no border");
})->with([
    'members list' => ['admin.users.index'],
    'treasury payments' => ['admin.treasury.payments'],
    'affiliations' => ['admin.users.registrations'],
    'articles' => ['admin.website.articles.index'],
    'tournaments' => ['admin.tournaments.index'],
]);

it('gives the admin card the same corner everywhere', function (): void {
    User::factory()->count(3)->create();

    $surface = json_decode((string) visit(route('admin.users.index'))->resize(1440, 900)->script(CARD_SURFACE), true);

    expect($surface['radii'])->toBe([12], 'cards should all round to 12px, the design system tile radius');
});
