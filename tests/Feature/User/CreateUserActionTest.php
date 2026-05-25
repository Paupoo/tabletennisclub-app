<?php

declare(strict_types=1);

use App\Mail\InviteNewUserMail;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

it('admin can create a new user', function (): void {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@gmail.com',
            'gender' => 'MEN',
        ])
        ->assertRedirect();

    expect(User::where('email', 'jean@gmail.com')->exists())->toBeTrue();
});

it('committee member can also create a new user', function (): void {
    Mail::fake();

    $member = User::factory()->isCommitteeMember()->create();

    $this->actingAs($member)
        ->post(route('users.store'), [
            'first_name' => 'Marie',
            'last_name' => 'Durand',
            'email' => 'marie@gmail.com',
            'gender' => 'WOMEN',
        ])
        ->assertRedirect();

    expect(User::where('email', 'marie@gmail.com')->exists())->toBeTrue();
});

it('regular user cannot create a new user (403)', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('users.store'), [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@test.com',
        ])
        ->assertStatus(403);
});

it('guest is redirected to login', function (): void {
    $this->post(route('users.store'), [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'guest@test.com',
    ])->assertRedirect(route('login'));
});

it('admin can re-invite an existing user', function (): void {
    Mail::fake();

    $existing = User::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('clubAdmin.users.invite-existing-user', $existing))
        ->assertRedirect();

    Mail::assertQueued(InviteNewUserMail::class);
});
