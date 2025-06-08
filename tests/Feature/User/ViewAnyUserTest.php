<?php

declare(strict_types=1);
uses(\Tests\Trait\CreateUser::class);

test('admin and committee members can see create member and force index buttons from index', function () {
    $admin = $this->createFakeAdmin();
    $committee_member = $this->createFakeCommitteeMember();

    $response = $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertSee([
            'Create new user',
            'Reset Force Index',
            'Delete Force Index',
        ]);

    $response = $this->actingAs($committee_member)
        ->get(route('users.index'))
        ->assertSee([
            'Create new user',
            'Reset Force Index',
            'Delete Force Index',
        ]);
});
test('admin and committee members can see edit and delete member buttons from index', function () {
    $user = $this->createFakeAdmin();

    $response = $this->actingAs($user)
        ->get(route('users.index'))
        ->assertSee([
            'Modify user details',
            'Update subscription payment status',
            'Delete user',
        ]);

    $user = $this->createFakeCommitteeMember();

    $response = $this->actingAs($user)
        ->get(route('users.index'))
        ->assertSee([
            'Modify user details',
            'Update subscription payment status',
            'Delete user',
        ]);
});
test('logged user can access members index', function () {
    $user = $this->createFakeUser();

    $response = $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk();
});
test('member cannot access create member page', function () {
    $user = $this->createFakeUser();

    $response = $this->actingAs($user)
        ->get(route('users.create'))
        ->assertStatus(403);
});
test('member cannot see create member and force index buttons', function () {
    $user = $this->createFakeUser();

    $response = $this->actingAs($user)
        ->get(route('users.index'))
        ->assertDontSee([
            'Create new user',
            'Reset Force Index',
            'Delete Force Index',
        ]);
});
test('member cannot see edit and delete member buttons from index', function () {
    $user = $this->createFakeUser();

    $response = $this->actingAs($user)
        ->get(route('users.index'))
        ->assertDontSee([
            'Modify user details',
            'Delete user',
        ])
        ->assertSee([
            'Check details',
        ]);
});
test('unlogged user cannot access members index', function () {
    $response = $this->get(route('users.index'))
        ->assertRedirect('/login');
});
