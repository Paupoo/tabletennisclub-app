<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;

/*
 * The members table grew two columns at 2xl: last activity and last sign-in.
 * The card offers 888 px at 1280: a column too many pushes the row off the
 * card, and the row's button with it. Measured on a row that fills every
 * column, under a long name of 28 characters.
 *
 * The page declares a font it never loads, so it renders in the machine's
 * fallback — narrower on a workstation than DejaVu Sans on the CI runner,
 * where a date column at xl overflowed by 48 px after passing locally. The
 * probe sets DejaVu Sans on every element so both measure the same thing.
 */
$tableProbe = <<<'JS'
(() => {
  document.querySelectorAll('*').forEach((el) => { el.style.fontFamily = '"DejaVu Sans"'; });
  const table = [...document.querySelectorAll('table')].find((t) => t.getClientRects().length > 0);
  if (!table) return { found: false };
  // Mary's wrapper around the table spans the card's content box.
  const card = table.parentElement;
  return {
    found: true,
    table: Math.round(table.getBoundingClientRect().right),
    card: Math.round(card.getBoundingClientRect().right),
    headers: [...table.querySelectorAll('thead th')].filter((th) => th.offsetWidth > 0).map((th) => th.textContent.trim()),
  };
})()
JS;

it('keeps the members table inside its card', function (int $width, int $height, bool $showsActivity) use ($tableProbe): void {
    $season = makeActiveSeason();

    $this->actingAs(User::factory()->withRole(Role::MEMBERS)->create());

    $member = User::factory()->create([
        'first_name' => 'Marie-Christine',
        'last_name' => 'Vanderlinden',
        'email' => 'jean-christophe.vandenbroucke@example.com',
        'ranking' => Ranking::C2->value,
    ]);
    $member->forceFill(['last_login_at' => now()->subDay()])->saveQuietly();
    Subscription::factory()->for($member)->for($season)->create(['status' => 'confirmed', 'is_competitive' => true]);

    $page = visit(route('admin.users.index'))->resize($width, $height);
    $page->assertNoJavaScriptErrors();

    $probe = $page->script($tableProbe);
    $p = $probe[0] ?? $probe;

    expect($p['found'])->toBeTrue()
        ->and(in_array(__('Last activity'), $p['headers'], true))->toBe($showsActivity)
        ->and(in_array(__('Last sign-in'), $p['headers'], true))->toBe($showsActivity)
        ->and($p['table'])->toBeLessThanOrEqual($p['card'], sprintf('the table ends at %d px, its card at %d px', $p['table'], $p['card']));
})->with([
    'xl, without the dates' => [1280, 800, false],
    '2xl, with both dates' => [1536, 900, true],
])->group('users');
