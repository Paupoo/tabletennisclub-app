<?php

declare(strict_types=1);

use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('admin and committee members can access edit member page', function (): void {
    $admin = $this->createFakeAdmin();
    $committee_member = $this->createFakeCommitteeMember();
    $user = $this->createFakeUser();

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $user))
        ->assertOK();

    $this->actingAs($committee_member)
        ->get(route('admin.users.edit', $user))
        ->assertOK();
});
test('unlogged user cannot access members edit', function (): void {
    $this->get(route('admin.users.edit', 1))
        ->assertRedirect('/login');
});
