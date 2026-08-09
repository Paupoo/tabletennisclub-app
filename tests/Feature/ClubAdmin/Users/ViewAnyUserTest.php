<?php

declare(strict_types=1);
use App\Domains\ClubAdmin\Users\Models\User;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

test('committee member can access members index', function (): void {
    makeActiveSeason();
    $user = User::factory()->isCommitteeMember()->create();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertOk();
});
test('plain member cannot access members index', function (): void {
    $user = $this->createFakeUser();

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});
test('unlogged user cannot access members index', function (): void {
    $response = $this->get(route('admin.users.index'))
        ->assertRedirect('/login');
});
