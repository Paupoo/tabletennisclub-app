<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

const SETTINGS_COMPONENT = 'pages::club-admin.users.user-space.settings';

it('lets the member update their password', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SETTINGS_COMPONENT, ['user' => $user])
        ->set('password', 'NewSecret!123')
        ->set('password_confirmation', 'NewSecret!123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('NewSecret!123', $user->fresh()->password))->toBeTrue();
});

it('rejects a password whose confirmation does not match', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SETTINGS_COMPONENT, ['user' => $user])
        ->set('password', 'NewSecret!123')
        ->set('password_confirmation', 'Different!123')
        ->call('updatePassword')
        ->assertHasErrors(['password']);
});

it('no longer renders disabled placeholder toggles', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SETTINGS_COMPONENT, ['user' => $user])
        ->assertDontSee(__('Public Profile'))
        ->assertDontSee(__('Show Phone Number'))
        ->assertDontSee(__('Match reminders (24h before)'));
});

it('announces upcoming notification preferences honestly', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SETTINGS_COMPONENT, ['user' => $user])
        ->assertSee(__('Coming soon'));
});
