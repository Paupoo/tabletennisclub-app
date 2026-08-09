<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Livewire\Livewire;

const PROFILE_FINES_COMPONENT = 'pages::club-admin.users.user-space.profile';

/** Almost nobody has a fine — the section must cost zero space for them. */
it('shows no fines section when the member has none', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertOk()
        ->assertDontSee(__('My fines'));
});

it('shows the fine with its reason, amount and the committee message', function (): void {
    $user = User::factory()->create();
    $fine = Fine::factory()->create([
        'user_id' => $user->id,
        'reason' => FineReason::UNJUSTIFIED_ABSENCE,
        'amount' => 15,
        'pedagogical_message' => 'Prevenez votre capitaine la prochaine fois.',
        'federation_reference' => 'AFTTB-2026-0042',
    ]);
    $fine->payment()->create([
        'reference' => '001/2026/00042',
        'amount_due' => 15,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertSee(__('My fines'))
        ->assertSee(__('Unjustified absence'))
        ->assertSee('15,00')
        ->assertSee('Prevenez votre capitaine la prochaine fois.')
        ->assertSee('AFTTB-2026-0042')
        ->assertSee(__('Pending'));
});

it('never shows another members fine', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Fine::factory()->create([
        'user_id' => $other->id,
        'pedagogical_message' => 'Secret message for someone else.',
    ]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertDontSee(__('My fines'))
        ->assertDontSee('Secret message for someone else.');
});

it('summarises the outstanding amount', function (): void {
    $user = User::factory()->create();
    $fine = Fine::factory()->create(['user_id' => $user->id, 'amount' => 20]);
    $fine->payment()->create([
        'reference' => '001/2026/00043',
        'amount_due' => 20,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertSee(__(':amount € still to pay', ['amount' => '20,00']));
});

it('shows fines as settled once paid', function (): void {
    $user = User::factory()->create();
    $fine = Fine::factory()->create(['user_id' => $user->id, 'amount' => 20]);
    $fine->payment()->create([
        'reference' => '001/2026/00044',
        'amount_due' => 20,
        'amount_paid' => 20,
        'status' => 'paid',
    ]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertSee(__('all settled'))
        ->assertSee(__('Paid'));
});
