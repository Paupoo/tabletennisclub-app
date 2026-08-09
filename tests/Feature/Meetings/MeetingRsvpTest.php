<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingRsvpConfirmationNotification;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/** Signed entry URL — valid for both the GET form and the same-URI POST. */
function rsvpUrl(Meeting $meeting, User $user): string
{
    return URL::signedRoute('meetings.rsvp', [
        'meeting' => $meeting->id,
        'user' => $user->id,
    ]);
}

function inviteUser(Meeting $meeting, User $user, MeetingUserStatusEnum $status = MeetingUserStatusEnum::INVITED): void
{
    $meeting->users()->attach($user->id, ['status' => $status->value]);
}

function registrationFor(Meeting $meeting, User $user)
{
    return $meeting->users()->where('users.id', $user->id)->first()?->registration;
}

// ── Show page ──────────────────────────────────────────────────────────────

describe('Meeting RSVP — show page', function (): void {
    test('shows the response form with attendance options', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->get(rsvpUrl($meeting, $user))
            ->assertOk()
            ->assertSee(__('Will you attend?'))
            ->assertDontSee(__('Reserve the meal'));
    });

    test('shows meal options only when the meeting has a meal', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->get(rsvpUrl($meeting, $user))
            ->assertOk()
            ->assertSee(__('Reserve the meal'));
    });
});

// ── Submitting ───────────────────────────────────────────────────────────────

describe('Meeting RSVP — submitting', function (): void {
    test('confirming attendance updates the pivot', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed']);

        $reg = registrationFor($meeting, $user);
        expect($reg->status)->toBe(MeetingUserStatusEnum::CONFIRMED)
            ->and($reg->response_at)->not->toBeNull();
    });

    test('declining attendance updates the pivot', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'declined']);

        expect(registrationFor($meeting, $user)->status)->toBe(MeetingUserStatusEnum::DECLINED);
    });

    test('confirming and reserving a paid meal creates a pending payment', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'reserve']);

        $reg = registrationFor($meeting, $user);
        expect($reg->meal_reserved)->toBeTrue();

        $payment = $reg->payment;
        expect($payment)->not->toBeNull()
            ->and($payment->status)->toBe('pending')
            ->and($payment->amount_due)->toBe(12.0);
    });

    test('confirming and skipping the meal creates no payment', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'skip']);

        expect(registrationFor($meeting, $user)->meal_reserved)->toBeFalse()
            ->and(Payment::count())->toBe(0);
    });

    test('switching from reserve to skip deletes the pending payment', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'reserve']);
        expect(Payment::count())->toBe(1);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'skip']);

        expect(Payment::count())->toBe(0)
            ->and(registrationFor($meeting, $user)->meal_reserved)->toBeFalse();
    });

    test('a paid meal stays locked when trying to un-reserve', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'reserve']);
        registrationFor($meeting, $user)->payment->update(['amount_paid' => 12, 'status' => 'paid']);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'skip']);

        $reg = registrationFor($meeting, $user);
        expect($reg->meal_reserved)->toBeTrue()
            ->and($reg->payment)->not->toBeNull();
    });

    test('a free meal can be reserved without creating a payment', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Soup', 0)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'reserve']);

        expect(registrationFor($meeting, $user)->meal_reserved)->toBeTrue()
            ->and(Payment::count())->toBe(0);
    });

    test('declining removes a pending meal payment', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'reserve']);
        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'declined']);

        expect(Payment::count())->toBe(0)
            ->and(registrationFor($meeting, $user)->status)->toBe(MeetingUserStatusEnum::DECLINED);
    });
});

// ── Confirmation email ─────────────────────────────────────────────────────────

describe('Meeting RSVP — confirmation email', function (): void {
    test('first confirmation sends the confirmation notification', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed']);

        Notification::assertSentTo($user, MeetingRsvpConfirmationNotification::class);
    });

    test('declining does NOT send a confirmation notification', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'declined']);

        Notification::assertNothingSent();
    });

    test('re-confirming does NOT send a second confirmation email', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user, MeetingUserStatusEnum::CONFIRMED);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed']);

        Notification::assertNothingSent();
    });

    test('the confirmation carries the payment only when the meal is reserved', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->withMeal('Pizzas', 1200)->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed', 'meal' => 'reserve']);

        Notification::assertSentTo($user, MeetingRsvpConfirmationNotification::class,
            fn (MeetingRsvpConfirmationNotification $n): bool => $n->payment !== null);
    });
});

// ── Security ───────────────────────────────────────────────────────────────────

describe('Meeting RSVP — security', function (): void {
    test('unsigned show URL is rejected with 403', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);

        $this->get(route('meetings.rsvp', ['meeting' => $meeting->id, 'user' => $user->id]))
            ->assertStatus(403);
    });

    test('unsigned submit URL is rejected with 403', function (): void {
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);

        $this->post(route('meetings.rsvp.submit', ['meeting' => $meeting->id, 'user' => $user->id]),
            ['attendance' => 'confirmed'])
            ->assertStatus(403);
    });

    test('an invalid attendance value is rejected', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);
        inviteUser($meeting, $user);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'maybe'])
            ->assertSessionHasErrors('attendance');
    });

    test('submitting attaches the user if not already invited', function (): void {
        Notification::fake();
        $admin = User::factory()->isAdmin()->create();
        $user = User::factory()->create([]);
        $meeting = Meeting::factory()->confirmed()->create(['created_by' => $admin->id]);

        $this->post(rsvpUrl($meeting, $user), ['attendance' => 'confirmed']);

        expect($meeting->users()->where('users.id', $user->id)->exists())->toBeTrue();
    });
});
