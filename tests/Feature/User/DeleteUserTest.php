<?php

declare(strict_types=1);
use App\Models\User;

uses(\Tests\Trait\CreateUser::class);

beforeEach(function () {
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

test('admin and committee member can see delete button from users index view', function () {
    
    $response = $this
        ->actingAs($this->admin)
        ->get(route('users.index'));

    $response->assertSee('Delete');

    $response = $this
        ->actingAs($this->committeeMember)
        ->get(route('users.index'));

    $response->assertSee('Delete');
});
test('admin and committee member can see delete button from users show view', function () {

    $response = $this
        ->actingAs($this->admin)
        ->get(route('users.show', $this->admin));

    $response->assertSee('Delete');

    $response = $this
        ->actingAs($this->committeeMember)
        ->get(route('users.show', $this->committeeMember));

    $response->assertSee('Delete');
});
test('admin or committee member delete a user from users index view', function () {

    $userToDelete1 = User::factory()->create();
    $userToDelete2 = User::factory()->create();

    $totalUsers = User::count();

    $response = $this
        ->actingAs($this->admin)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $userToDelete1));

    $response
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $response = $this
        ->actingAs($this->committeeMember)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $userToDelete2));

    $response
        ->assertRedirect(route('users.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseCount('users', $totalUsers - 2);
});
test('user cant delete any user', function () {
    $userToDelete = User::find(1);

    $response = $this
        ->actingAs($this->user)
        ->from(route('users.index'))
        ->delete(route('users.destroy', $userToDelete));

    $response
        ->assertStatus(403);
});
test('user cant see delete button from users index view', function () {

    $response = $this
        ->actingAs($this->user)
        ->get(route('users.index'));

    $response->assertDontSee('Delete');
});
test('user cant see delete button from users show view', function () {

    $response = $this
        ->actingAs($this->user)
        ->get(route('users.show', $this->user));

    $response->assertDontSee('Delete');
});
