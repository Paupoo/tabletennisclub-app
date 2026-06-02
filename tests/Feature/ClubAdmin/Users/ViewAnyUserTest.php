<?php

declare(strict_types=1);
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('logged user can access members index', function (): void {
    $user = $this->createFakeUser();

    $response = $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertOk();
});
test('unlogged user cannot access members index', function (): void {
    $response = $this->get(route('admin.users.index'))
        ->assertRedirect('/login');
});
