<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Fines\Models\Fine;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\FineReason;
use Livewire\Livewire;

const PROFILE_FINES_COMPONENT = 'pages::club-admin.users.user-space.profile';

beforeEach(function (): void {
    fineCreditorConfigured();
});

/** Almost nobody has a fine — the section must cost zero space for them. */
it('shows no fines section when the member has none', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertOk()
        ->assertDontSee(__('My fines'));
});

it('shows the fine with its reason, event, amount and the committee message', function (): void {
    $user = User::factory()->create();
    Fine::factory()->create([
        'user_id' => $user->id,
        'reason' => FineReason::UNANNOUNCED_ABSENCE,
        'amount' => 35,
        'event_label' => 'LA HULPE RIXENSART',
        'pedagogical_message' => 'Prevenez votre capitaine la prochaine fois.',
    ]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertSee(__('My fines'))
        ->assertSee(FineReason::UNANNOUNCED_ABSENCE->label())
        ->assertSee('LA HULPE RIXENSART')
        ->assertSee('35,00')
        ->assertSee('Prevenez votre capitaine la prochaine fois.');
});

/*
 * The member who lost the mail finds everything to pay the committee here, as
 * long as paying still keeps their qualification.
 */
it('shows how to pay the committee until the deadline', function (): void {
    $user = User::factory()->create(['first_name' => 'Jeremy', 'last_name' => 'Denil']);
    $fine = Fine::factory()->create(['user_id' => $user->id, 'payment_deadline' => today()]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertSee(__('How to pay'))
        ->assertSee('CPBBW')
        ->assertSee('BE50 2100 3624 5518')
        ->assertSee($fine->transferCommunication())
        ->assertSee('data:image/png;base64,', false);
});

it('keeps a fine past its deadline as history only', function (): void {
    $user = User::factory()->create();
    Fine::factory()->pastDeadline()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertSee(__('My fines'))
        ->assertDontSee(__('How to pay'))
        ->assertDontSee('BE50 2100 3624 5518');
});

/*
 * The club is never told whether the member paid: a "pending" badge would stay
 * wrong forever, and a "paid" one could never be earned.
 */
it('shows no payment status the club could not know', function (): void {
    $user = User::factory()->create();
    Fine::factory()->create(['user_id' => $user->id]);

    Livewire::actingAs($user)
        ->test(PROFILE_FINES_COMPONENT, ['user' => $user])
        ->assertDontSee(__('Pending'))
        ->assertDontSee(__('all settled'));
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
