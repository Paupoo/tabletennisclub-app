<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Meeting;

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Notifications\MeetingRsvpConfirmationNotification;
use App\Domains\Shared\Enums\MeetingUserStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingRsvpController extends Controller
{
    public function handle(Request $request, Meeting $meeting, User $user, string $response): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $newStatus = match ($response) {
            'confirmed' => MeetingUserStatusEnum::CONFIRMED,
            'declined' => MeetingUserStatusEnum::DECLINED,
            default => abort(404),
        };

        // Statut actuel avant mise à jour
        $currentStatus = $meeting->users()->where('users.id', $user->id)->first()?->registration?->status;
        $wasAlreadyConfirmed = in_array($currentStatus, [
            MeetingUserStatusEnum::CONFIRMED,
            MeetingUserStatusEnum::ATTENDED,
        ], true);

        $meeting->users()->syncWithoutDetaching([
            $user->id => [
                'status' => $newStatus->value,
                'response_at' => now(),
            ],
        ]);

        $payment = null;

        // Email de confirmation + paiement uniquement au premier "confirmed"
        if ($newStatus === MeetingUserStatusEnum::CONFIRMED && ! $wasAlreadyConfirmed) {
            if ($meeting->has_meal && ($meeting->meal_price_cents ?? 0) > 0) {
                $registration = $meeting->users()->where('users.id', $user->id)->first()->registration;

                $payment = $registration->payment()->create([
                    'reference' => (new GeneratePaymentReference)(),
                    'amount_due' => $meeting->meal_price, // accessor euros → setter convertit en centimes
                    'amount_paid' => 0,
                    'status' => 'pending',
                ]);
            }

            $user->notify(new MeetingRsvpConfirmationNotification($meeting, $payment));
        }

        $meeting->loadMissing('agendaItems');

        return view('meetings.rsvp-confirmation', [
            'meeting' => $meeting,
            'user' => $user,
            'response' => $newStatus,
            'wasAlreadyConfirmed' => $wasAlreadyConfirmed,
            'payment' => $payment,
        ]);
    }
}
