<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
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

test('admin and committee member can see delete button from users index view', function (): void {

    $response = $this
        ->actingAs($this->admin)
        ->get(route('admin.users.index'));

    $response->assertSee('Delete');

    $response = $this
        ->actingAs($this->committeeMember)
        ->get(route('admin.users.index'));

    $response->assertSee('Delete');
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
