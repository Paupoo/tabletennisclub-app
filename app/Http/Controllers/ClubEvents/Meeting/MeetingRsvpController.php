<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Meeting;

use App\Actions\ClubAdmin\Payments\GeneratePaymentQR;
use App\Actions\Meetings\RespondToMeetingRsvp;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Meetings\Models\Meeting;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeetingRsvpController extends Controller
{
    public function show(Request $request, Meeting $meeting, User $user): View
    {
        abort_unless($request->hasValidSignature(), 403);

        $meeting->loadMissing('agendaItems');

        $registration = $meeting->users()->where('users.id', $user->id)->first()?->registration;
        $payment = $registration?->payment;

        return view('meetings.rsvp', [
            'meeting' => $meeting,
            'user' => $user,
            'registration' => $registration,
            'payment' => $payment,
            'paymentQr' => $payment ? (new GeneratePaymentQR)($payment) : null,
        ]);
    }

    public function submit(Request $request, Meeting $meeting, User $user): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $validated = $request->validate([
            'attendance' => ['required', 'string', 'in:confirmed,declined'],
            'meal' => [
                Rule::requiredIf($request->input('attendance') === 'confirmed' && $meeting->has_meal),
                'nullable',
                'string',
                'in:reserve,skip',
            ],
        ]);

        $mealReserved = match ($validated['meal'] ?? null) {
            'reserve' => true,
            'skip' => false,
            default => null,
        };

        (new RespondToMeetingRsvp)($meeting, $user, $validated['attendance'] === 'confirmed', $mealReserved);

        return redirect()->back()->with('status', __('Your response has been saved. Thank you!'));
    }
}
