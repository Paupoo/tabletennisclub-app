<?php

declare(strict_types=1);

use App\Actions\User\RestoreUserAction;
use App\Actions\User\SoftDeleteUserAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Mail\InviteNewUserMail;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
});

test('delete action soft deletes user and excludes from default list', function (): void {
    $user = User::factory()->create();

    SoftDeleteUserAction::handle($user);

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull();
});

test('index page delete uses soft delete via action', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $user->id)
        ->call('delete');

    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id)->deleted_at)->not->toBeNull();
});

test('restore action restores soft deleted user', function (): void {
    $user = User::factory()->create();
    $user->delete();

    RestoreUserAction::handle($user);

    expect(User::find($user->id))->not->toBeNull();
});

test('index page can restore archived user', function (): void {
    $user = User::factory()->create();
    $user->delete();

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('restoreUser', $user->id);

    expect(User::find($user->id))->not->toBeNull();
});

test('archived users appear when showArchived is true', function (): void {
    $active = User::factory()->create();
    $archived = User::factory()->create();
    $archived->delete();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->set('showArchived', true);

    $component->assertSee($archived->email);
});

test('archived users not shown by default', function (): void {
    $archived = User::factory()->create(['email' => 'archived@test.com']);
    $archived->delete();

    $component = Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index');

    $component->assertDontSee('archived@test.com');
});

test('admin cannot delete themselves via single delete', function (): void {
    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $this->admin->id)
        ->call('delete');

    expect(User::find($this->admin->id))->not->toBeNull();
});

test('admin is excluded from bulk delete even when selected', function (): void {
    $other = User::factory()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->set('selected', [(string) $this->admin->id, (string) $other->id])
        ->call('deleteSelected');

    expect(User::find($this->admin->id))->not->toBeNull()
        ->and(User::find($other->id))->toBeNull();
});

test('resend invitation uses SendInvitationAction and queues mail', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email_verified_at' => null,
        'last_invited_at' => now()->subDays(3),
    ]);

    Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('sendInvitation', $user->id);

    Mail::assertQueued(InviteNewUserMail::class);
    expect($user->fresh()->last_invited_at)->not->toBeNull();
});
