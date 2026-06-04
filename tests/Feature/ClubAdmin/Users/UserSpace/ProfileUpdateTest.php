<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('user can update their own contact fields', function (): void {
    $user = User::factory()->create([
        'email' => 'original@example.com',
        'phone_number' => '0470000000',
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('email', 'updated@example.com')
        ->set('phone_number', '0479999999')
        ->call('save');

    expect($user->fresh()->email)->toBe('updated@example.com')
        ->and($user->fresh()->phone_number)->toBe('0479999999');
});

test('user can update identity fields', function (): void {
    $user = User::factory()->create([
        'first_name' => 'Jean',
        'last_name' => 'Dupont',
    ]);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('first_name', 'Pierre')
        ->set('last_name', 'Martin')
        ->call('save');

    expect($user->fresh()->first_name)->toBe('Pierre')
        ->and($user->fresh()->last_name)->toBe('Martin');
});

test('user cannot update another users profile', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'other@example.com']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $other])
        ->set('email', 'hacked@example.com')
        ->call('save');

    expect($other->fresh()->email)->toBe('other@example.com');
});

test('admin can update any users profile', function (): void {
    $admin = $this->createFakeAdmin();
    $user = User::factory()->create(['phone_number' => '0470000000']);

    Livewire::actingAs($admin)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('phone_number', '0479111111')
        ->call('save');

    expect($user->fresh()->phone_number)->toBe('0479111111');
});

test('email must be unique across users', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->set('email', 'taken@example.com')
        ->call('save')
        ->assertHasErrors(['email']);
});

test('user can request GDPR erasure', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.profile', ['user' => $user])
        ->call('requestErasure');

    expect($user->fresh())->not->toBeNull();
});
