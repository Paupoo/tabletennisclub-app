<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Enums\CommitteeRolesEnum;
use App\Enums\TournamentStatusEnum;
use App\Events\Tournament\UserRegisteredToTournament;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use App\Models\ClubEvents\Tournament\TournamentRegistration;
use App\Notifications\Payment\RefundRequestedNotification;
use App\Notifications\Tournament\TournamentConfirmationExpiredNotification;
use App\Notifications\Tournament\TournamentPaymentReminderNotification;
use App\Notifications\Tournament\TournamentPaymentRequestNotification;
use App\Notifications\Tournament\TournamentRegistrationCancelledNotification;
use App\Notifications\Tournament\TournamentRegistrationConfirmedNotification;
use App\Notifications\Tournament\TournamentWaitlistSpotOpenedNotification;
use Event;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    /**
     * Cancel a registration and, if the user was actively registered, promote the next person on the waiting list.
     * - If payment is pending: mark it cancelled (no money moved).
     * - If payment is paid: mark it to_refund and notify the treasurer + secretary.
     */
    public function cancelRegistration(Tournament $tournament, User $user): void
    {
        $registration = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        $wasActivelyRegistered = in_array(
            $registration?->registration_status,
            ['registered', 'confirmed', 'spot_offered'],
            true,
        );

        if ($registration?->payment_id) {
            $payment = $registration->payment;

            if ($payment?->status === 'paid') {
                $payment->update(['status' => 'to_refund']);
                $this->notifyTreasurerAndSecretary($payment, $user, $tournament);
            } elseif ($payment?->status === 'pending') {
                $payment->update(['status' => 'cancelled']);
            }
        }

        DB::table('tournament_user')
            ->where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->update(['registration_status' => 'cancelled', 'waitlist_position' => null]);

        $user->notify(new TournamentRegistrationCancelledNotification($tournament));

        $this->countRegisteredUsers($tournament);

        if ($wasActivelyRegistered) {
            $this->openSpot($tournament);
        } else {
            // Waiting user withdrew — renumber remaining positions.
            $this->recalculateWaitlistPositions($tournament);
        }
    }

    /**
     * Recount active (registered/confirmed) registrations and sync the denormalized total_users.
     */
    public function countRegisteredUsers(Tournament $tournament): int
    {
        $count = $tournament->activeRegistrationsCount();
        $tournament->update(['total_users' => $count]);

        return $count;
    }

    /**
     * Expire unconfirmed waitlist promotions (48h window passed) and trigger the next person.
     * Called by the hourly scheduler.
     */
    public function expireConfirmationDeadlines(): void
    {
        $expired = TournamentRegistration::where('registration_status', 'spot_offered')
            ->where('confirmation_deadline', '<', now())
            ->get();

        foreach ($expired as $registration) {
            DB::table('tournament_user')
                ->where('id', $registration->id)
                ->update(['registration_status' => 'cancelled', 'confirmation_deadline' => null]);

            $user = User::find($registration->user_id);
            $tournament = Tournament::find($registration->tournament_id);

            if ($user && $tournament) {
                $user->notify(new TournamentConfirmationExpiredNotification($tournament));
                $this->countRegisteredUsers($tournament);
                $this->openSpot($tournament);
            }
        }
    }

    /**
     * Expire unpaid registrations (72h window passed) and trigger waitlist.
     * Called by the daily scheduler.
     */
    public function expirePaymentDeadlines(): void
    {
        $expired = TournamentRegistration::where('registration_status', 'registered')
            ->where('has_paid', false)
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '<', now())
            ->get();

        foreach ($expired as $registration) {
            DB::table('tournament_user')
                ->where('id', $registration->id)
                ->update(['registration_status' => 'cancelled']);

            $tournament = Tournament::find($registration->tournament_id);

            if ($tournament) {
                $this->countRegisteredUsers($tournament);
                $this->openSpot($tournament);
            }
        }
    }

    /**
     * True when the tournament has a cap and active registrations have reached it.
     */
    public function isFull(Tournament $tournament): bool
    {
        return $tournament->max_users > 0
            && $tournament->activeRegistrationsCount() >= $tournament->max_users;
    }

    /**
     * Promote the next waiting-list person to 'registered' and send the 48-hour confirmation email.
     * Recalculates waitlist positions for remaining members after the promotion.
     */
    public function openSpot(Tournament $tournament): void
    {
        $next = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('registration_status', 'waiting')
            ->orderBy('waitlist_position')
            ->first();

        if ($next === null) {
            return;
        }

        $deadline = now()->addHours(48);

        DB::table('tournament_user')
            ->where('id', $next->id)
            ->update([
                'registration_status' => 'spot_offered',
                'waitlist_position' => null,
                'confirmation_deadline' => $deadline,
            ]);

        $this->recalculateWaitlistPositions($tournament);

        $user = User::find($next->user_id);

        if ($user) {
            $user->notify(new TournamentWaitlistSpotOpenedNotification(
                tournament: $tournament,
                userId: $user->id,
                deadline: $deadline,
            ));
        }
    }

    /**
     * Register a user. Adds them to the waiting list if the tournament is full.
     * Creates a Payment for paid tournaments (deadline = registration deadline, or +3 days if registered late).
     * If registered on tournament day, no payment email is sent (paid on site).
     *
     * @throws \LogicException when the user is already registered or on the waitlist.
     * @throws \LogicException when registrations are closed.
     */
    public function registerUser(Tournament $tournament, User $user): void
    {
        $hasActiveRegistration = $tournament->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('registration_status', ['registered', 'confirmed', 'waiting', 'spot_offered'])
            ->exists();

        if ($hasActiveRegistration) {
            throw new \LogicException('This player is already registered to this tournament.');
        }

        if ($tournament->status !== TournamentStatusEnum::PUBLISHED) {
            throw new \LogicException('Registrations are closed for this tournament.');
        }

        if ($this->isFull($tournament)) {
            $this->addToWaitlist($tournament, $user);

            return;
        }

        // Re-registration after cancellation: update existing row instead of attaching.
        $existingRow = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingRow) {
            $existingRow->update([
                'registration_status' => 'registered',
                'has_paid' => false,
                'payment_id' => null,
                'confirmation_deadline' => null,
                'payment_deadline' => null,
                'waitlist_position' => null,
            ]);
            $registration = $existingRow->fresh();
        } else {
            $tournament->users()->attach($user->id, ['registration_status' => 'registered']);
            $registration = TournamentRegistration::where('tournament_id', $tournament->id)
                ->where('user_id', $user->id)
                ->firstOrFail();
        }

        // Always send confirmation first.
        $user->notify(new TournamentRegistrationConfirmedNotification($tournament));

        if ($tournament->isPaid()) {
            $isOnTournamentDay = $tournament->start_date
                && now()->startOfDay()->equalTo($tournament->start_date->copy()->startOfDay());

            if (! $isOnTournamentDay) {
                $registrationDeadline = $tournament->registration_deadline;
                $isLateRegistration = $registrationDeadline && now()->gt($registrationDeadline);
                $deadline = $isLateRegistration
                    ? now()->addDays(3)->endOfDay()
                    : ($registrationDeadline?->copy()->endOfDay() ?? now()->addDays(3)->endOfDay());

                $payment = $registration->payment()->create([
                    'reference' => (new GeneratePaymentReference)(),
                    'amount_due' => $tournament->price,
                    'amount_paid' => 0,
                    'status' => 'pending',
                ]);

                DB::table('tournament_user')
                    ->where('id', $registration->id)
                    ->update([
                        'payment_id' => $payment->id,
                        'payment_deadline' => $deadline,
                    ]);

                $user->notify(new TournamentPaymentRequestNotification(
                    tournament: $tournament,
                    payment: $payment,
                    deadline: $deadline,
                ));
            }
        }

        $this->countRegisteredUsers($tournament);

        Event::dispatch(new UserRegisteredToTournament($tournament, $user));
    }

    /**
     * Send daily payment reminders for registrations still pending payment.
     * Called by the daily scheduler.
     */
    public function sendPaymentReminders(): void
    {
        $pending = TournamentRegistration::where('registration_status', 'registered')
            ->where('has_paid', false)
            ->whereNotNull('payment_deadline')
            ->where('payment_deadline', '>', now())
            ->with(['tournament'])
            ->get();

        foreach ($pending as $registration) {
            $user = User::find($registration->user_id);
            $payment = $registration->payment;

            if ($user && $payment && $registration->tournament) {
                $user->notify(new TournamentPaymentReminderNotification(
                    tournament: $registration->tournament,
                    payment: $payment,
                    deadline: $registration->payment_deadline,
                ));
            }
        }
    }

    /**
     * Unregister all users and reset the counter.
     */
    public function unregisterAllUsers(Tournament $tournament): int
    {
        $count = $tournament->users()->detach();
        $tournament->update(['total_users' => 0]);

        return $count;
    }

    private function addToWaitlist(Tournament $tournament, User $user): void
    {
        $position = $tournament->nextWaitlistPosition();

        $existingRow = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingRow) {
            $existingRow->update([
                'registration_status' => 'waiting',
                'waitlist_position' => $position,
                'has_paid' => false,
                'payment_id' => null,
                'confirmation_deadline' => null,
                'payment_deadline' => null,
            ]);
        } else {
            $tournament->users()->attach($user->id, [
                'registration_status' => 'waiting',
                'waitlist_position' => $position,
            ]);
        }

        $user->notify(new TournamentRegistrationConfirmedNotification(
            tournament: $tournament,
            isWaitlisted: true,
            waitlistPosition: $position,
        ));
    }

    /**
     * Send an immediate refund alert to all committee members with TREASURER or SECRETARY role.
     */
    private function notifyTreasurerAndSecretary(
        Payment $payment,
        User $user,
        Tournament $tournament,
    ): void {
        User::where('is_committee_member', true)
            ->whereIn('committee_role', [
                CommitteeRolesEnum::TREASURER->value,
                CommitteeRolesEnum::SECRETARY->value,
            ])
            ->get()
            ->each->notify(new RefundRequestedNotification($payment, $user, $tournament));
    }

    /**
     * Renumber waitlist positions sequentially starting at 1, preserving relative order.
     * Call this after any promotion or cancellation that removes someone from the waitlist.
     */
    private function recalculateWaitlistPositions(Tournament $tournament): void
    {
        $waiting = TournamentRegistration::where('tournament_id', $tournament->id)
            ->where('registration_status', 'waiting')
            ->orderBy('waitlist_position')
            ->get();

        foreach ($waiting as $index => $registration) {
            $registration->update(['waitlist_position' => $index + 1]);
        }
    }
}
