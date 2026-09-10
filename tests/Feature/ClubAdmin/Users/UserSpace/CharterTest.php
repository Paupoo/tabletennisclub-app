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

it('redirects a guest to login', function (): void {
    $user = User::factory()->create();

    $this->get(route('admin.user.charter', $user))
        ->assertRedirect(route('login'));
});

it('asks interclub players to arrive 45 minutes early, and says what the setup is for', function (): void {
    $user = User::factory()->create();

    // Spelled out in French rather than through __(): a charter is a text members
    // are held to, so a silent edit to the translation must fail here too.
    Livewire::actingAs($user)
        ->test(CHARTER_COMPONENT, ['user' => $user])
        ->assertSee("Heure d'arrivée\u{A0}: au minimum 45 minutes avant le début officiel du match.")
        ->assertSee("s'échauffer 30 min avant le début du match");
});

it('ties the bar rota to playing at home, and makes the home captains answerable', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CHARTER_COMPONENT, ['user' => $user])
        ->assertSee("chaque équipe délègue un membre en alternance lorsqu'elle joue à domicile")
        ->assertSee('les capitaines des équipes à domicile sont coresponsables du service au bar')
        ->assertDontSee('une fois toutes les six semaines');
});
