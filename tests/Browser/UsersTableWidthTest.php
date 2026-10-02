<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Ranking;
use App\Domains\Shared\Enums\Role;

/*
 * The members table grew a column at xl (last activity) and another at 2xl
 * (last sign-in). The card offers 888 px at 1280: a column too many pushes the
 * row off the card, and the row's button with it. Measured on a row that
 * fills every column, under a long name of 28 characters — at xl, its address
 * is capped tighter to make room for the date.
 */
$tableProbe = <<<'JS'
(() => {
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

it('keeps the members table inside its card with the activity columns', function (int $width, int $height, string $column) use ($tableProbe): void {
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
        ->and($p['headers'])->toContain(__($column))
        ->and($p['table'])->toBeLessThanOrEqual($p['card'], sprintf('the table ends at %d px, its card at %d px', $p['table'], $p['card']));
})->with([
    'xl' => [1280, 800, 'Last activity'],
    '2xl' => [1536, 900, 'Last sign-in'],
])->group('users');
