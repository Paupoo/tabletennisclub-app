<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

// ── Helpers ───────────────────────────────────────────────────────────────────

function createTournamentPayment(User $user, Tournament $tournament, string $status = 'pending')
{
    $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

    $registration = TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $user->id)
        ->first();

    return $registration->payment()->create([
        'reference' => '001/2026/00001',
        'amount_due' => 10,
        'amount_paid' => 0,
        'status' => $status,
    ]);
}

function mountEventSubscription(User $user)
{
    return Livewire::actingAs($user)
        ->test('pages::club-admin.users.user-space.event-subscription', ['user' => $user]);
}

function futureMealMeeting(): Meeting
{
    $admin = User::factory()->create(['is_admin' => true]);

    return Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create([
        'created_by' => $admin->id,
        'scheduled_at' => now()->addWeek(),
        'ends_at' => now()->addWeek()->addHours(2),
    ]);
}

// ── pendingPayments ───────────────────────────────────────────────────────────

describe('pendingPayments', function (): void {
    it('returns the user pending tournament payment', function (): void {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        createTournamentPayment($user, $tournament);

        $component = mountEventSubscription($user);

        expect($component->get('pendingPayments'))->toHaveCount(1);
    });

    it('excludes pending payments belonging to another user', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $tournament = paymentTournament();
        createTournamentPayment($otherUser, $tournament);

        $component = mountEventSubscription($user);

        expect($component->get('pendingPayments'))->toHaveCount(0);
    });

    it('excludes paid payments', function (): void {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        createTournamentPayment($user, $tournament, 'paid');

        $component = mountEventSubscription($user);

        expect($component->get('pendingPayments'))->toHaveCount(0);
    });
});

// ── openPaymentModal ──────────────────────────────────────────────────────────

describe('openPaymentModal', function (): void {
    beforeEach(function (): void {
        Club::factory()->ownClub()->create();
    });
    it('opens the modal and stores the payment id', function (): void {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        $payment = createTournamentPayment($user, $tournament);

        mountEventSubscription($user)
            ->call('openPaymentModal', $payment->id)
            ->assertSet('paymentModal', true)
            ->assertSet('selectedPaymentId', $payment->id);
    });

    it('generates a non-empty QR code string', function (): void {
        $user = User::factory()->create();
        $tournament = paymentTournament();
        $payment = createTournamentPayment($user, $tournament);

        $component = mountEventSubscription($user)
            ->call('openPaymentModal', $payment->id);

        expect($component->get('paymentQr'))->toStartWith('data:image/png;base64,');
    });

    it('renders the tournament name in the modal', function (): void {
        $user = User::factory()->create();
        $tournament = paymentTournament(['name' => 'Summer Cup 2026']);
        $payment = createTournamentPayment($user, $tournament);

        mountEventSubscription($user)
            ->call('openPaymentModal', $payment->id)
            ->assertSee('Summer Cup 2026');
    });
});

// ── Meeting RSVP (in-app) ───────────────────────────────────────────────────────

describe('meeting rsvp', function (): void {
    it('opens the modal prefilled from the registration', function (): void {
        $user = User::factory()->create();
        $meeting = futureMealMeeting();
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'meal_reserved' => true]);

        mountEventSubscription($user)
            ->call('openMeetingRsvp', $meeting->id)
            ->assertSet('meetingRsvpModal', true)
            ->assertSet('rsvpMeetingId', $meeting->id)
            ->assertSet('rsvpAttendance', 'confirmed')
            ->assertSet('rsvpMeal', 'reserve');
    });

    it('reserves the meal and creates a pending payment', function (): void {
        Notification::fake();
        $user = User::factory()->create();
        $meeting = futureMealMeeting();
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        mountEventSubscription($user)
            ->set('rsvpMeetingId', $meeting->id)
            ->set('rsvpAttendance', 'confirmed')
            ->set('rsvpMeal', 'reserve')
            ->call('saveMeetingRsvp')
            ->assertSet('meetingRsvpModal', false);

        $reg = $meeting->users()->where('users.id', $user->id)->first()->registration;
        expect($reg->meal_reserved)->toBeTrue()
            ->and($reg->payment)->not->toBeNull()
            ->and($reg->payment->status)->toBe('pending');
    });

    it('skips the meal without creating a payment', function (): void {
        Notification::fake();
        $user = User::factory()->create();
        $meeting = futureMealMeeting();
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        mountEventSubscription($user)
            ->set('rsvpMeetingId', $meeting->id)
            ->set('rsvpAttendance', 'confirmed')
            ->set('rsvpMeal', 'skip')
            ->call('saveMeetingRsvp');

        expect(Payment::count())->toBe(0)
            ->and($meeting->users()->where('users.id', $user->id)->first()->registration->meal_reserved)->toBeFalse();
    });

    it('shows the real status and meal badge on the meeting row', function (): void {
        $user = User::factory()->create();
        $meeting = futureMealMeeting();
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value, 'meal_reserved' => true]);

        mountEventSubscription($user)
            ->assertSee($meeting->title)
            ->assertSee(__('Meal · pending'))
            ->assertSee(__('Manage'));
    });
});
