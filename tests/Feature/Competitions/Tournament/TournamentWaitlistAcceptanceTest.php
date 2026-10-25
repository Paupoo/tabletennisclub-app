<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationConfirmedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

/*
 * Taking a spot from the waiting list is registering. The player who accepts
 * gets the payment request every other entrant got, at their own price — and
 * the same status whichever door they came through.
 */

beforeEach(function (): void {
    Club::factory()->ownClub()->create();
    Notification::fake();
    Carbon::setTestNow('2026-11-10 10:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * @return array{0: Tournament, 1: User}
 */
function offeredSpot(?string $birthdate = null): array
{
    $tournament = paymentTournament([
        'price' => 15,
        'reduced_price' => 10,
        'reduced_price_under_age' => 12,
        'start_date' => '2026-11-29',
        'registration_deadline' => '2026-11-20',
    ]);
    $member = User::factory()->create(['birthdate' => $birthdate]);
    $tournament->users()->attach($member->id, [
        'registration_status' => 'spot_offered',
        'confirmation_deadline' => now()->addHours(48),
    ]);

    return [$tournament, $member];
}

function acceptedRegistration(Tournament $tournament, User $member): TournamentRegistration
{
    return TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $member->id)->firstOrFail();
}

it('registers the player who accepts through the e-mail link, with a payment request', function (): void {
    [$tournament, $member] = offeredSpot();

    $this->get(URL::signedRoute('tournament.register.email', ['tournament' => $tournament->id, 'user' => $member->id]))
        ->assertRedirect();

    $registration = acceptedRegistration($tournament, $member);
    expect($registration->registration_status)->toBe('registered')
        ->and($registration->payment?->amount_due)->toBe(15.0)
        ->and($registration->payment_deadline?->format('Y-m-d H:i'))->toBe('2026-11-20 23:59');
    Notification::assertSentTo($member, TournamentRegistrationConfirmedNotification::class);
});

it('registers the player who accepts from their space, at their own price', function (): void {
    [$tournament, $member] = offeredSpot(birthdate: '2016-05-01');

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member])
        ->call('confirmTournamentSpot', $tournament->id);

    $registration = acceptedRegistration($tournament, $member);
    expect($registration->registration_status)->toBe('registered')
        ->and($registration->payment?->amount_due)->toBe(10.0);
    Notification::assertSentTo($member, TournamentRegistrationConfirmedNotification::class);
});

it('gives a late acceptance three days to pay', function (): void {
    [$tournament, $member] = offeredSpot();
    Carbon::setTestNow('2026-11-22 10:00');

    Livewire::actingAs($member)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $member])
        ->call('confirmTournamentSpot', $tournament->id);

    expect(acceptedRegistration($tournament, $member)->payment_deadline?->format('Y-m-d'))->toBe('2026-11-25');
});
