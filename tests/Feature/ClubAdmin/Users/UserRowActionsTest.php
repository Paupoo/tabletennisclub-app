<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
});

/*
 * A member with no address of their own cannot be invited: the invitation hands
 * over a login, and sent to a guardian it would set a password on somebody
 * else's account. That reason used to live in the tooltip of a greyed-out icon,
 * which is nowhere at all under a thumb.
 *
 * The action is removed and the reason written instead.
 */
test('a member who cannot be invited is told why, not shown a dead control', function (): void {
    // email_verified_at is not fillable, so passing it to the factory is silently
    // dropped and the member comes back already active — with no invitation to offer.
    User::factory()->create(['email' => null, 'last_name' => 'Sansadresse'])
        ->forceFill(['email_verified_at' => null])->save();

    $html = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->html();

    $reason = e(__('This member has no address of their own yet, so they cannot be invited.'));

    // Blade escapes apostrophes, so the copy must be compared in its rendered form.
    // The discriminating part is where the reason lives: it used to be the aria-label
    // of a greyed-out icon — announced by nothing, reachable by no thumb — and it has
    // to be visible text instead.
    expect($html)
        ->toContain($reason)
        ->not->toContain('aria-label="' . $reason . '"');
});

test('a member with an address keeps the invitation among the secondary actions', function (): void {
    User::factory()->create(['email' => 'joignable@example.com', 'last_name' => 'Joignable'])
        ->forceFill(['email_verified_at' => null])->save();

    $html = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->html();

    expect($html)->toContain(e(__('Resend invitation')));
});
