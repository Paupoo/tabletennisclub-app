<?php

declare(strict_types=1);

use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Meeting\Meeting;
use App\Notifications\Meeting\MeetingRsvpConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

function rsvpUrl(Meeting $meeting, User $user, string $response): string
{
    return URL::signedRoute('meetings.rsvp', [
        'meeting' => $meeting->id,
        'user' => $user->id,
        'response' => $response,
    ]);
}

describe('Meeting RSVP — confirmation page', function () {
    test('confirming shows the confirmation page and updates pivot', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'))
            ->assertOk()
            ->assertSee('Attendance confirmed!');

        $pivot = $meeting->users()->where('users.id', $user->id)->first()->registration;
        expect($pivot->status)->toBe(MeetingUserStatusEnum::CONFIRMED)
            ->and($pivot->response_at)->not->toBeNull();
    });

    test('declining shows the declined page', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        $this->get(rsvpUrl($meeting, $user, 'declined'))
            ->assertOk()
            ->assertSee('Attendance declined');

        $pivot = $meeting->users()->where('users.id', $user->id)->first()->registration;
        expect($pivot->status)->toBe(MeetingUserStatusEnum::DECLINED);
    });

    test('re-confirming shows the already-attending state', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'))
            ->assertOk()
            ->assertSee('You are already attending');
    });
});

describe('Meeting RSVP — confirmation email', function () {
    test('first confirmation sends confirmation notification', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'));

        Notification::assertSentTo($user, MeetingRsvpConfirmationNotification::class);
    });

    test('declining does NOT send a confirmation notification', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        $this->get(rsvpUrl($meeting, $user, 'declined'));

        Notification::assertNothingSent();
    });

    test('re-confirming does NOT send a second confirmation email', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'));

        Notification::assertNothingSent();
    });
});

describe('Meeting RSVP — meal payment', function () {
    test('confirming a paid-meal meeting generates a pending payment', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'));

        $registration = $meeting->users()->where('users.id', $user->id)->first()->registration;
        $payment = Payment::where('payable_type', $registration->getMorphClass())
            ->where('payable_id', $registration->id)
            ->first();

        expect($payment)->not->toBeNull()
            ->and($payment->status)->toBe('pending')
            ->and($payment->amount_due)->toBe(12.0); // 1200 cents → 12.00 €
    });

    test('confirming a free meeting does NOT generate a payment', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]); // no meal
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::INVITED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'));

        expect(Payment::count())->toBe(0);
    });

    test('re-confirming does NOT generate a duplicate payment', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        $meeting->users()->attach($user->id, ['status' => MeetingUserStatusEnum::CONFIRMED->value]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'));

        expect(Payment::count())->toBe(0);
    });
});

describe('Meeting RSVP — security', function () {
    test('unsigned RSVP URL is rejected with 403', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);

        $this->get(route('meetings.rsvp', [
            'meeting' => $meeting->id,
            'user' => $user->id,
            'response' => 'confirmed',
        ]))->assertStatus(403);
    });

    test('invalid response value returns 404', function () {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);

        $this->get(rsvpUrl($meeting, $user, 'maybe'))->assertStatus(404);
    });

    test('rsvp attaches user if not already in pivot', function () {
        Notification::fake();

        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_active' => true]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);

        $this->get(rsvpUrl($meeting, $user, 'confirmed'))->assertOk();

        expect($meeting->users()->where('users.id', $user->id)->exists())->toBeTrue();
    });
});
