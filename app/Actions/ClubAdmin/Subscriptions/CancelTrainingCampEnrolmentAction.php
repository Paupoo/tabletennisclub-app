<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackEnrolmentCancelledNotification;
use App\Domains\Trainings\Services\TrainingCampBilling;
use App\Domains\Trainings\Services\TrainingPackExit;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Undoes a stage enrolment that should never have existed.
 *
 * The stage counterpart of {@see CancelTrainingPackEnrolmentAction}, with one
 * difference: the line is not deleted but marked `cancelled`. It carries the
 * stage's payments, and a refund needs something to hang on. Nothing is owed
 * on a cancelled line, so the unpaid request is cancelled and what came in
 * leaves as a refund.
 *
 * Refused once the coach has marked the member other than absent: they came,
 * and that is a departure, which stays owed in full.
 */
final readonly class CancelTrainingCampEnrolmentAction
{
    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * @return float the amount sent to refund, in euros
     *
     * @throws \DomainException
     */
    public function __invoke(Subscription $subscription, TrainingPack $camp): float
    {
        $line = $this->billing->line($subscription, $camp);

        if ($line === null || ! in_array($line->status, ['enrolled', 'left'], true)) {
            throw new \DomainException(__('Only a validated training pack can be cancelled as an encoding error.'));
        }

        $exit = new TrainingPackExit;
        $marked = $exit->markedSessionsCount($subscription, $camp);

        if ($marked > 0) {
            throw new \DomainException(trans_choice(
                '{1}The coach marked this member at one session: they came, so this is a departure, not an encoding error.|[2,*]The coach marked this member at :count sessions: they came, so this is a departure, not an encoding error.',
                $marked,
                ['count' => $marked],
            ));
        }

        $previousStatus = $line->status;
        $claimedBefore = $this->billing->outstandingClaim($line);

        [$refunded, $absences] = DB::transaction(function () use ($line, $subscription, $camp, $exit): array {
            $absences = $exit->eraseAbsences($subscription, $camp);

            $line->forceFill(['status' => 'cancelled', 'ends_on' => null])->save();

            $refunded = $this->billing->sync($line, __('The :pack training camp enrolment of :member was an encoding error.', [
                'pack' => $camp->name,
                'member' => $subscription->user->full_name,
            ]))['refunded'];

            return [$refunded, $absences];
        });

        $reduced = max(0.0, round($claimedBefore - $this->billing->outstandingClaim($line) - $refunded, 2));

        if ($previousStatus === 'enrolled') {
            app(TrainingWaitlistService::class)->releaseSpot($camp);
        }

        activity()
            ->performedOn($subscription)
            ->causedBy(Auth::user())
            ->event('training_camp_enrolment_cancelled')
            ->withProperties([
                'training_pack_id' => $camp->id,
                'training_pack' => $camp->name,
                'status' => $previousStatus,
                'override_amount' => $line->override_amount,
                'absences_erased' => $absences,
                'claim_reduced' => $reduced,
                'refundable' => $refunded,
            ])
            ->log('training_camp_enrolment_cancelled');

        $subscription->user->notify(new TrainingPackEnrolmentCancelledNotification($camp, $subscription, $reduced, $refunded));

        return $refunded;
    }
}
