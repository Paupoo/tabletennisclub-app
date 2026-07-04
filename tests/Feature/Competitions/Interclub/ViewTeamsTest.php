<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;

test('committee member can see teams index', function (): void {
    makeActiveSeason();
    $user = User::factory()->isCommitteeMember()->create();
    $response = $this->actingAs($user)
        ->get(route('admin.interclubs.teams'));

    $response->assertStatus(200);
});

test('plain member cannot see teams index', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.interclubs.teams'))
        ->assertForbidden();
});
test('unlogged user cant see teams index', function (): void {
    $response = $this->get(route('admin.interclubs.teams'));

    $response->assertRedirect('/login');
});
