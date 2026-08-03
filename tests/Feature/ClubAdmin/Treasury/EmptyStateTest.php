<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Livewire\Livewire;

/*
 * An empty screen is a new club's first screen. Stating that nothing is there
 * and stopping leaves the treasurer with no next move, so the empty state has to
 * carry the action that fills it — here, importing a bank statement.
 *
 * Screens with nothing to create (an empty queue, no failed jobs) are
 * deliberately excluded: there is no action to offer and inventing one would be
 * noise.
 */
test('the payments screen offers a way out when it is empty', function (): void {
    $treasurer = User::factory()->withRole(Role::TREASURY)->create();

    $html = Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->html();

    $message = __('No payments to display.');

    expect($html)->toContain($message);

    // The header menu links to the same route, so the assertion has to look inside
    // the empty state itself rather than anywhere on the page.
    $block = substr($html, (int) strpos($html, (string) $message));
    $block = substr($block, 0, 800);

    expect($block)->toMatch('/<a\s[^>]*href=/');
});
