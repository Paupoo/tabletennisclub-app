<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Notifications\RefundRequestedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentConfirmationExpiredNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentPaymentExpiredNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationCancelledNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentRegistrationConfirmedNotification;
use App\Domains\Competitions\Tournament\Notifications\TournamentWaitlistSpotOpenedNotification;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use App\Domains\Shared\Enums\TournamentStatusEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('counts registered users and returns correct user count', function (): void {
    $tournament = Tournament::factory()->create();

    $users = User::factory()->count(5)->create();
    $tournament->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);

    $service = new TournamentService;
    $count = $service->countRegisteredUsers($tournament);

    expect($count)->toBe(5);
    expect($tournament->fresh()->total_users)->toBe(5);
});

it('counts only active registrations, not waitlisted or cancelled', function (): void {
    $tournament = Tournament::factory()->create(['max_users' => 2]);

    $active = User::factory()->count(2)->create();
    $waiting = User::factory()->create();
    $cancelled = User::factory()->create();

    $tournament->users()->attach($active->pluck('id'), ['registration_status' => 'registered']);
    $tournament->users()->attach($waiting->id, ['registration_status' => 'waiting', 'waitlist_position' => 1]);
    $tournament->users()->attach($cancelled->id, ['registration_status' => 'cancelled']);

    $service = new TournamentService;
    $count = $service->countRegisteredUsers($tournament);

    expect($count)->toBe(2);
    expect($service->isFull($tournament))->toBeTrue();
});

it('checks if a tournament is full using real DB count', function (): void {
    $service = new TournamentService;

    $notFull = Tournament::factory()->create(['max_users' => 3]);
    $users = User::factory()->count(2)->create();
    $notFull->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);
    expect($service->isFull($notFull))->toBeFalse();

    $full = Tournament::factory()->create(['max_users' => 2]);
    $full->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);
    expect($service->isFull($full))->toBeTrue();

    $unlimited = Tournament::factory()->create(['max_users' => 0]);
    $unlimited->users()->attach($users->pluck('id'), ['registration_status' => 'registered']);
    expect($service->isFull($unlimited))->toBeFalse();
});

// ── registerUser ──────────────────────────────────────────────────────────────

describe('registerUser', function (): void {
    it('registers a user to a free tournament with no payment', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0, 'max_users' => 10]);
        Event::fake();

        $user = User::factory()->create();
        (new TournamentService)->registerUser($tournament, $user);

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('registered');

        Notification::assertSentTo($user, TournamentRegistrationConfirmedNotification::class);
        expect(Payment::count())->toBe(0);
    });

    it('creates a pending payment for a paid tournament', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10, 'max_users' => 10]);
        Event::fake();

        $user = User::factory()->create();
        (new TournamentService)->registerUser($tournament, $user);

        expect(Payment::count())->toBe(1);
        expect(Payment::first()->status)->toBe('pending');
    });

    // The payment deadline is NOT a fixed 72h window (I6): these lock the real rule.
    it('sets the payment deadline to the registration-close date for a normal sign-up', function (): void {
        Notification::fake();
        Event::fake();
        $tournament = paymentTournament([
            'price' => 10,
            'registration_deadline' => Carbon::create(2026, 9, 15, 12),
            'start_date' => Carbon::create(2026, 9, 20, 10),
        ]);

        (new TournamentService)->registerUser($tournament, User::factory()->create());

        expect(TournamentRegistration::first()->payment_deadline->format('Y-m-d H:i:s'))
            ->toBe('2026-09-15 23:59:59');
    });

    it('gives a late sign-up 3 days to pay, not the past registration date', function (): void {
        Notification::fake();
        Event::fake();
        $tournament = paymentTournament([
            'price' => 10,
            'registration_deadline' => now()->subDays(2),
            'start_date' => now()->addDays(5),
        ]);

        (new TournamentService)->registerUser($tournament, User::factory()->create());

        expect(TournamentRegistration::first()->payment_deadline->toDateString())
            ->toBe(now()->addDays(3)->toDateString());
    });

    it('sets no payment deadline for a same-day entry', function (): void {
        Notification::fake();
        Event::fake();
        $tournament = paymentTournament([
            'price' => 10,
            'registration_deadline' => now()->subDay(),
            'start_date' => now(),
        ]);

        (new TournamentService)->registerUser($tournament, User::factory()->create());

        expect(TournamentRegistration::first()->payment_deadline)->toBeNull();
    });

    it('throws LogicException when user is already registered', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0, 'max_users' => 10]);
        Event::fake();

        $user = User::factory()->create();
        (new TournamentService)->registerUser($tournament, $user);

        expect(fn () => (new TournamentService)->registerUser($tournament, $user))
            ->toThrow(LogicException::class);
    });

    it('throws LogicException when tournament is not PUBLISHED', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['status' => TournamentStatusEnum::DRAFT, 'price' => 0]);
        Event::fake();

        $user = User::factory()->create();

        expect(fn () => (new TournamentService)->registerUser($tournament, $user))
            ->toThrow(LogicException::class);
    });

    it('adds user to waitlist when tournament is full', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0, 'max_users' => 1]);
        Event::fake();

        $firstUser = User::factory()->create();
        (new TournamentService)->registerUser($tournament, $firstUser);

        $waitingUser = User::factory()->create();
        (new TournamentService)->registerUser($tournament, $waitingUser);

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $waitingUser->id)
                ->value('registration_status')
        )->toBe('waiting');
    });

    it('increments total_users on the tournament', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0, 'max_users' => 10, 'total_users' => 0]);
        Event::fake();

        $user = User::factory()->create();
        (new TournamentService)->registerUser($tournament, $user);

        expect($tournament->fresh()->total_users)->toBe(1);
    });
});

// ── cancelRegistration ────────────────────────────────────────────────────────

describe('cancelRegistration', function (): void {
    it('cancels a registered user and sends notification', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0, 'max_users' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'registered']);
        Event::fake();

        (new TournamentService)->cancelRegistration($tournament, $user);

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('cancelled');

        Notification::assertSentTo($user, TournamentRegistrationCancelledNotification::class);
    });

    it('marks a pending payment as cancelled', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10, 'max_users' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

        $registration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $payment = $registration->payment()->create([
            'reference' => 'TEST/001',
            'amount_due' => 1000,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);
        $registration->update(['payment_id' => $payment->id]);

        Event::fake();
        (new TournamentService)->cancelRegistration($tournament, $user);

        expect($payment->fresh()->status)->toBe('cancelled');
    });

    it('marks a paid payment as to_refund', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10, 'max_users' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, ['registration_status' => 'registered']);

        $registration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $payment = $registration->payment()->create([
            'reference' => 'TEST/002',
            'amount_due' => 1000,
            'amount_paid' => 1000,
            'status' => 'paid',
        ]);
        $registration->update(['payment_id' => $payment->id]);

        Event::fake();
        (new TournamentService)->cancelRegistration($tournament, $user);

        expect($payment->fresh()->status)->toBe('to_refund');
    });

    it('renders the treasurer refund email with a link to the member profile', function (): void {
        $tournament = paymentTournament(['price' => 10, 'max_users' => 10]);
        $member = User::factory()->create();
        $treasurer = User::factory()->isCommitteeMember()->create();
        $tournament->users()->attach($member->id, ['registration_status' => 'registered']);

        $registration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $payment = $registration->payment()->create([
            'reference' => 'TEST/003',
            'amount_due' => 1000,
            'amount_paid' => 1000,
            'status' => 'to_refund',
        ]);

        $mail = new RefundRequestedNotification($payment, $member, $tournament)->toMail($treasurer);
        $html = $mail->render()->__toString();

        expect($html)->toContain(route('admin.users.edit', $member->id));
    });
});

// ── openSpot ──────────────────────────────────────────────────────────────────

describe('openSpot', function (): void {
    it('promotes the first waiting user to spot_offered', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0, 'max_users' => 1]);

        $waitingUser = User::factory()->create();
        $tournament->users()->attach($waitingUser->id, [
            'registration_status' => 'waiting',
            'waitlist_position' => 1,
        ]);

        Event::fake();
        (new TournamentService)->openSpot($tournament);

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $waitingUser->id)
                ->value('registration_status')
        )->toBe('spot_offered');

        Notification::assertSentTo($waitingUser, TournamentWaitlistSpotOpenedNotification::class);
    });

    it('does nothing when no one is waiting', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0]);

        Event::fake();
        (new TournamentService)->openSpot($tournament);

        Notification::assertNothingSent();
    });

    it('promotes the user with the lowest waitlist_position', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0]);

        $first = User::factory()->create();
        $second = User::factory()->create();
        $tournament->users()->attach($first->id, ['registration_status' => 'waiting', 'waitlist_position' => 1]);
        $tournament->users()->attach($second->id, ['registration_status' => 'waiting', 'waitlist_position' => 2]);

        Event::fake();
        (new TournamentService)->openSpot($tournament);

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $first->id)
                ->value('registration_status')
        )->toBe('spot_offered');

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $second->id)
                ->value('registration_status')
        )->toBe('waiting');
    });
});

// ── expireConfirmationDeadlines ───────────────────────────────────────────────

describe('expireConfirmationDeadlines', function (): void {
    it('cancels spot_offered registrations past their deadline', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'spot_offered',
            'confirmation_deadline' => now()->subHour(),
        ]);

        Event::fake();
        (new TournamentService)->expireConfirmationDeadlines();

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('cancelled');

        Notification::assertSentTo($user, TournamentConfirmationExpiredNotification::class);
    });

    it('does not cancel spot_offered registrations with future deadlines', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 0]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'spot_offered',
            'confirmation_deadline' => now()->addHour(),
        ]);

        Event::fake();
        (new TournamentService)->expireConfirmationDeadlines();

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('spot_offered');
    });
});

// ── expirePaymentDeadlines ────────────────────────────────────────────────────

describe('expirePaymentDeadlines', function (): void {
    it('cancels registered + unpaid registrations past their payment deadline', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'registered',
            'has_paid' => false,
            'payment_deadline' => now()->subHour(),
        ]);

        Event::fake();
        (new TournamentService)->expirePaymentDeadlines();

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('cancelled');
    });

    it('notifies the member when their unpaid registration is cancelled (I5)', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'registered',
            'has_paid' => false,
            'payment_deadline' => now()->subHour(),
        ]);

        Event::fake();
        (new TournamentService)->expirePaymentDeadlines();

        Notification::assertSentTo($user, TournamentPaymentExpiredNotification::class);
    });

    it('renders the payment-expired email fully in the member locale', function (): void {
        app()->setLocale('fr_BE');
        $tournament = paymentTournament(['price' => 10]);
        $user = User::factory()->create();

        $rendered = (string) new TournamentPaymentExpiredNotification($tournament)->toMail($user)->render();

        expect($rendered)->toContain('paiement')
            ->and($rendered)->not->toContain('has been cancelled because we did not receive')
            ->and($rendered)->not->toContain('Your registration has expired');
    });

    it('does not notify when nothing expires', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'registered',
            'has_paid' => false,
            'payment_deadline' => now()->addDay(),
        ]);

        Event::fake();
        (new TournamentService)->expirePaymentDeadlines();

        Notification::assertNotSentTo($user, TournamentPaymentExpiredNotification::class);
    });

    it('does not cancel registrations with future payment deadlines', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'registered',
            'has_paid' => false,
            'payment_deadline' => now()->addDay(),
        ]);

        Event::fake();
        (new TournamentService)->expirePaymentDeadlines();

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('registered');
    });

    it('does not cancel registrations with no payment_deadline', function (): void {
        Notification::fake();
        $tournament = paymentTournament(['price' => 10]);
        $user = User::factory()->create();
        $tournament->users()->attach($user->id, [
            'registration_status' => 'registered',
            'has_paid' => false,
            'payment_deadline' => null,
        ]);

        Event::fake();
        (new TournamentService)->expirePaymentDeadlines();

        expect(
            TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->value('registration_status')
        )->toBe('registered');
    });
});
