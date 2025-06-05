<?php

declare(strict_types=1);
use App\Models\User;

test('logged user can see teams index', function () {
    $user = User::find(1);
    $response = $this->actingAs($user)
        ->get(route('teams.index'));

    $response->assertStatus(200);
});
test('unlogged user cant see teams index', function () {
    $response = $this->get(route('teams.index'));

    $response->assertRedirect('/login');
});
