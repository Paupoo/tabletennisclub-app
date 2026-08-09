<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

// ── sharesContact (opt-in defaults) ─────────────────────────────────────────────

it('hides every contact field by default (opt-in)', function (string $field): void {
    $user = User::factory()->create();

    expect($user->sharesContact($field))->toBeFalse();
})->with(['phone', 'email', 'address']);

it('reports a field as shared once the member opts in', function (): void {
    $user = User::factory()->create(['contact_visibility' => ['phone' => true]]);

    expect($user->sharesContact('phone'))->toBeTrue()
        ->and($user->sharesContact('email'))->toBeFalse();
});

// ── contactVisibleTo (central visibility rule) ──────────────────────────────────

it('always lets a member see their own contact fields', function (): void {
    $user = User::factory()->create(); // nothing shared

    expect($user->contactVisibleTo($user, 'phone'))->toBeTrue();
});

it('hides an unshared field from another member', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    expect($owner->contactVisibleTo($viewer, 'phone'))->toBeFalse();
});

it('shows a shared field to another member', function (): void {
    $owner = User::factory()->create(['contact_visibility' => ['phone' => true]]);
    $viewer = User::factory()->create();

    expect($owner->contactVisibleTo($viewer, 'phone'))->toBeTrue();
});

it('always shows unshared fields to admins and committee members', function (): void {
    $owner = User::factory()->create(); // nothing shared
    $admin = $this->createFakeAdmin();
    $committee = $this->createFakeCommitteeMember();

    expect($owner->contactVisibleTo($admin, 'phone'))->toBeTrue()
        ->and($owner->contactVisibleTo($committee, 'address'))->toBeTrue();
});

// ── settings toggles ────────────────────────────────────────────────────────────

it('persists a contact-visibility toggle from the settings page', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.settings', ['user' => $user])
        ->assertSet('sharePhone', false)
        ->set('sharePhone', true);

    expect($user->fresh()->sharesContact('phone'))->toBeTrue();
});

it('rejects toggling another members contact visibility', function (): void {
    $attacker = User::factory()->create();
    $victim = User::factory()->create();

    Livewire::actingAs($attacker)
        ->test('pages::club-admin.users.user-space.settings', ['user' => $victim])
        ->assertForbidden();
});
