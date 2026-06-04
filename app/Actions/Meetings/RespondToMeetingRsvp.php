<?php

declare(strict_types=1);

namespace App\Actions\Meetings;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingRsvpConfirmationNotification;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;

/**
 * Single source of truth for an invitee RSVP: records attendance + the optional
 * meal reservation, reconciles the meal payment, and emails the confirmation on
 * the first attendance. Used by both the signed e-mail page and the in-app one.
 */
class RespondToMeetingRsvp
{
    public function __invoke(Meeting $meeting, User $user, bool $attending, ?bool $mealReserved = null): ?Payment
    {
        $registration = $meeting->users()->where('users.id', $user->id)->first()?->registration;

        $wasAttending = $registration !== null && in_array($registration->status, [
            MeetingUserStatusEnum::CONFIRMED,
            MeetingUserStatusEnum::ATTENDED,
        ], true);

        // A paid meal is immutable online — keep the reservation whatever happens.
        $locked = $registration !== null && $registration->mealPaymentLocked();

        $mealReservedValue = match (true) {
            ! $meeting->has_meal => null,            // no catering on this meeting
            $locked => true,            // already paid
            ! $attending => false,           // declining => no meal
            default => $mealReserved === true,
        };

        $status = $attending ? MeetingUserStatusEnum::CONFIRMED : MeetingUserStatusEnum::DECLINED;

        $meeting->users()->syncWithoutDetaching([
            $user->id => [
                'status' => $status->value,
                'response_at' => now(),
                'meal_reserved' => $mealReservedValue,
                'meal_responded_at' => $meeting->has_meal ? now() : null,
            ],
        ]);

        $registration = $meeting->users()->where('users.id', $user->id)->first()->registration;
        $payment = $registration->payment;

        if ($mealReservedValue === true && ($meeting->meal_price_cents ?? 0) > 0) {
            if ($payment === null) {
                $payment = $registration->payment()->create([
                    'reference' => (new GeneratePaymentReference)(),
                    'amount_due' => $meeting->meal_price, // euros accessor → cents setter
                    'amount_paid' => 0,
                    'status' => 'pending',
                ]);
            }
        } elseif ($payment !== null && ! $locked) {
            // Meal dropped while still unpaid → remove the pending payment.
            $payment->delete();
            $payment = null;
        }

        if ($attending && ! $wasAttending) {
            $user->notify(new MeetingRsvpConfirmationNotification($meeting, $payment));
        }

        return $payment;
    }
}
