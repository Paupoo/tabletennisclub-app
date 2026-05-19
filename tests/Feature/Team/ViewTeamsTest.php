<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;

test('logged user can see teams index', function (): void {
    $user = User::factory()->create();
    $response = $this->actingAs($user)
        ->get(route('admin.interclubs.teams'));

    $response->assertStatus(200);
});
test('unlogged user cant see teams index', function (): void {
    $response = $this->get(route('admin.interclubs.teams'));

    $response->assertRedirect('/login');
});
