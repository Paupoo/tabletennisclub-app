<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\Tournament;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Competitions\Tournament\Notifications\TournamentPaymentReminderNotification;
use App\Domains\Competitions\Tournament\Services\TournamentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/*
 * An unpaid registration is a debt, never a reason to drop a player.
 *
 * A transfer made on time can sit unreconciled for days, and nothing tells
 * "has not paid" apart from "paid, statement not imported yet". The member
 * keeps their spot and settles at the desk if it comes to that; what they get
 * is two reminders, not one an hour.
 */

/**
 * A member registered to a paid tournament whose payment is due on the 20th.
 *
 * @return array{0: Tournament, 1: User}
 */
function unpaidEntrant(): array
{
    Notification::fake();
    Carbon::setTestNow('2026-11-01 10:00');

    $tournament = paymentTournament([
        'start_date' => '2026-11-29',
        'registration_deadline' => '2026-11-20',
    ]);
    $member = User::factory()->create();

    app(TournamentService::class)->registerUser($tournament, $member);
    Notification::fake();

    return [$tournament, $member];
}

function processDeadlinesAt(string $moment): void
{
    Carbon::setTestNow($moment);
    test()->artisan('tournament:process-deadlines')->assertSuccessful();
}

afterEach(fn () => Carbon::setTestNow());

it('keeps an unpaid player registered once the payment deadline has passed', function (): void {
    [$tournament, $member] = unpaidEntrant();

    processDeadlinesAt('2026-11-22 10:00');

    expect(TournamentRegistration::where('tournament_id', $tournament->id)
        ->where('user_id', $member->id)
        ->value('registration_status'))->toBe('registered');
});

it('sends no reminder while the deadline is more than three days away', function (): void {
    [, $member] = unpaidEntrant();

    processDeadlinesAt('2026-11-16 10:00');

    Notification::assertNotSentTo($member, TournamentPaymentReminderNotification::class);
});

it('reminds an unpaid player once, three days before the deadline, however often the scheduler runs', function (): void {
    [, $member] = unpaidEntrant();

    processDeadlinesAt('2026-11-17 09:00');
    processDeadlinesAt('2026-11-17 10:00');
    processDeadlinesAt('2026-11-19 10:00');

    Notification::assertSentToTimes($member, TournamentPaymentReminderNotification::class, 1);
});

it('reminds an unpaid player once more the day after the deadline, offering to pay on site', function (): void {
    [, $member] = unpaidEntrant();

    processDeadlinesAt('2026-11-17 09:00');
    processDeadlinesAt('2026-11-21 09:00');
    processDeadlinesAt('2026-11-21 10:00');
    processDeadlinesAt('2026-11-25 10:00');

    Notification::assertSentToTimes($member, TournamentPaymentReminderNotification::class, 2);
    Notification::assertSentTo(
        $member,
        TournamentPaymentReminderNotification::class,
        fn (TournamentPaymentReminderNotification $notification): bool => $notification->isOverdue,
    );
});

it('never threatens to cancel the registration in a reminder', function (): void {
    Club::factory()->ownClub()->create();
    [$tournament, $member] = unpaidEntrant();
    $registration = TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $member->id)->firstOrFail();

    foreach ([false, true] as $isOverdue) {
        $mail = (string) new TournamentPaymentReminderNotification(
            tournament: $tournament,
            payment: $registration->payment,
            deadline: $registration->payment_deadline,
            isOverdue: $isOverdue,
        )->toMail($member)->render();

        expect($mail)->not->toContain('cancelled automatically');
    }
});

it('stops reminding a player who has paid', function (): void {
    [$tournament, $member] = unpaidEntrant();
    $registration = TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $member->id)->firstOrFail();
    (new AllocateTransactionAction)->credit($registration->payment, (float) $registration->payment->amount_due, 'cash');

    processDeadlinesAt('2026-11-17 09:00');
    processDeadlinesAt('2026-11-21 09:00');

    Notification::assertNotSentTo($member, TournamentPaymentReminderNotification::class);
});

it('gives a player who registers on the deadline day until the end of that day to pay', function (): void {
    Notification::fake();
    Carbon::setTestNow('2026-11-20 18:00');
    $tournament = paymentTournament(['start_date' => '2026-11-29', 'registration_deadline' => '2026-11-20']);
    $member = User::factory()->create();

    app(TournamentService::class)->registerUser($tournament, $member);

    $deadline = TournamentRegistration::where('tournament_id', $tournament->id)->where('user_id', $member->id)->firstOrFail()->payment_deadline;

    expect($deadline?->format('Y-m-d H:i'))->toBe('2026-11-20 23:59');
});
