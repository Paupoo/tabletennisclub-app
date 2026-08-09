<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

const REGLEMENT_COMPONENT = 'pages::club-admin.users.user-space.reglement';

it('shows the four rule chapters to a member', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(REGLEMENT_COMPONENT, ['user' => $user])
        ->assertOk()
        ->assertSee(__('How an interclub meeting unfolds'))
        ->assertSee(__('Essential rules of play'))
        ->assertSee(__('Conduct, sanctions & fines'))
        ->assertSee(__('Rankings, promotion & relegation'));
});

it('links to the official AFTTB regulation', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(REGLEMENT_COMPONENT, ['user' => $user])
        ->assertSee(__('Full AFTTB regulation'));
});

it('redirects a guest to login', function (): void {
    $user = User::factory()->create();

    $this->get(route('admin.user.reglement', $user))
        ->assertRedirect(route('login'));
});
