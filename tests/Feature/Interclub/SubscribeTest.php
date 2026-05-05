<?php

declare(strict_types=1);

use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Interclub;

it('allows a user to subscribe to interclubs', function () {
    $user = User::factory()->create();
    $interclub = Interclub::factory()->create();

    $this->actingAs($user)
        ->post(route('interclubs.subscription'), [
            'subscriptions' => [(string) $interclub->id => '1'],
        ])
        ->assertRedirect(route('interclubs.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('interclub_user', [
        'interclub_id' => $interclub->id,
        'user_id' => $user->id,
        'is_subscribed' => true,
    ]);
});
