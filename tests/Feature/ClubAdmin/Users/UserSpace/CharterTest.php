<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Livewire\Livewire;

const CHARTER_COMPONENT = 'pages::club-admin.users.user-space.charter';

it('shows the six charter chapters to a member', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CHARTER_COMPONENT, ['user' => $user])
        ->assertOk()
        ->assertSee(__('Trainings'))
        ->assertSee(__('Competitions and interclubs'))
        ->assertSee(__('Running the bar'))
        ->assertSee(__('Welcome and hospitality'))
        ->assertSee(__('Tidying up and closing'))
        ->assertSee(__('Rotating responsibilities'));
});

it('states why each chapter exists, not only what it asks', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CHARTER_COMPONENT, ['user' => $user])
        ->assertSee(__('Why it matters'))
        ->assertSee(__('A tidy club is a club we respect. It is a matter of safety, of durability and of professionalism.'));
});

it('closes on the three values of the club', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CHARTER_COMPONENT, ['user' => $user])
        ->assertSee(__('Respect'))
        ->assertSee(__('Sharing'))
        ->assertSee(__('Solidarity'));
});

it('sends the bar chapter to the live price list rather than repeating prices', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CHARTER_COMPONENT, ['user' => $user])
        ->assertSee(route('bar.index'));
});

it('redirects a guest to login', function (): void {
    $user = User::factory()->create();

    $this->get(route('admin.user.charter', $user))
        ->assertRedirect(route('login'));
});
