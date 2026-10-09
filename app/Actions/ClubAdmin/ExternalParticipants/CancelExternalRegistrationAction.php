<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\ExternalParticipants;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a non-member's registration that should never have existed.
 *
 * The registration is kept as `cancelled`, never deleted: it carries the
 * payments, and a refund needs something to hang on. Nothing is owed any
 * more, so the unpaid invoice is cancelled and what came in leaves as a
 * refund. The place goes back to the members waiting in line.
 *
 * Refused once the coach marked them other than absent: they came, and that
 * is a withdrawal, owed in full. Their absences go with the registration.
 */
final readonly class CancelExternalRegistrationAction
{
    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * @return float the amount sent to refund, in euros
     *
     * @throws \DomainException
     */
    public function __invoke(ExternalRegistration $registration): float
    {
        if (! $registration->isOwed()) {
            throw new \DomainException(__('This registration is already cancelled.'));
        }

        $came = $registration->trainings()->wherePivot('status', '!=', 'absent')->count();

        if ($came > 0) {
            throw new \DomainException(trans_choice(
                '{1}The coach marked this participant at one session: they came, so this is a withdrawal, not an encoding error.|[2,*]The coach marked this participant at :count sessions: they came, so this is a withdrawal, not an encoding error.',
                $came,
                ['count' => $came],
            ));
        }

        $wasSeated = $registration->status === 'enrolled';
        $camp = $registration->registrable;

        $refunded = DB::transaction(function () use ($registration, $camp): float {
            $registration->trainings()->detach();
            $registration->update(['status' => 'cancelled']);

            return $this->billing->sync($registration, __('The :pack training camp registration of :participant was an encoding error.', [
                'pack' => $camp->name,
                'participant' => $registration->displayName(),
            ]))['refunded'];
        });

        if ($wasSeated) {
            app(TrainingWaitlistService::class)->releaseSpot($camp);
        }

        return $refunded;
    }
}
