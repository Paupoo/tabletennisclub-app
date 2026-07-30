<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = User::factory()
        ->isAdmin()
        ->create();
    $this->committeeMember = User::factory()
        ->isCommitteeMember()
        ->create();
    $this->user = User::factory()
        ->create();
}
);

test('admin can see delete button for other users', function (): void {
    $response = $this
        ->actingAs($this->admin)
        ->get(route('admin.users.index'));

    $response->assertSee('Archive');
});

test('admin account is not archived when they try to delete themselves', function (): void {
    Livewire\Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $this->admin->id)
        ->call('delete');

    expect(User::find($this->admin->id))->not->toBeNull();
});

test('committee member cannot see delete button from users index view', function (): void {
    $response = $this
        ->actingAs($this->committeeMember)
        ->get(route('admin.users.index'));

    $response->assertDontSee('Delete user');
});
test('user cant see delete button from users index view', function (): void {

    $response = $this
        ->actingAs($this->user)
        ->get(route('admin.users.index'));

    $response->assertDontSee('Delete user');
});
test('user cant see delete button from users show view', function (): void {

    $response = $this
        ->actingAs($this->user)
        ->get(route('admin.user.profile', $this->user));

    $response->assertDontSee('Delete user');
});

test('archiving a user with an unresolved subscription for the active season shows an error and keeps them active', function (): void {
    $season = makeActiveSeason();
    Subscription::factory()->create([
        'user_id' => $this->user->id,
        'season_id' => $season->id,
        'status' => 'paid',
    ]);

    Livewire\Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $this->user->id)
        ->call('delete');

    expect(User::find($this->user->id))->not->toBeNull();
});

test('archiving a user whose active-season subscription is already cancelled succeeds', function (): void {
    $season = makeActiveSeason();
    Subscription::factory()->create([
        'user_id' => $this->user->id,
        'season_id' => $season->id,
        'status' => 'cancelled',
    ]);

    Livewire\Livewire::actingAs($this->admin)
        ->test('pages::club-admin.users.index')
        ->call('confirmDelete', $this->user->id)
        ->call('delete');

    expect(User::find($this->user->id))->toBeNull();
});
