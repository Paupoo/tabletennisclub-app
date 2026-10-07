<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Notifications\TrainingPackEnrolmentCancelledNotification;
use App\Domains\Trainings\Services\TrainingPackExit;
use App\Domains\Trainings\Services\TrainingWaitlistService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Défait une inscription qui n'aurait jamais dû exister : une erreur d'encodage.
 *
 * Ce n'est pas un départ ({@see LeaveTrainingPackAction}). Un départ garde la
 * ligne et facture les mois suivis ; ici rien n'a été suivi, la ligne disparaît
 * et le prix se recalcule comme si elle n'avait jamais été posée. Ce qui est
 * encore réclamé baisse, ce qui est déjà rentré est rendu en entier.
 *
 * Une ligne `left` s'annule aussi : c'est ainsi qu'on rattrape une erreur déjà
 * retirée par un départ, qui gardait ses mois facturés.
 *
 * Refusée dès que le coach a pointé le membre autrement qu'absent : il est
 * venu, ce n'est plus une erreur, c'est un départ. Les absences, elles, ont été
 * écrites par la validation des séances parce que le membre figurait dans la
 * liste — elles partent avec la ligne. La ligne de pivot ne garde aucune trace :
 * le journal d'activité la garde à sa place.
 */
class CancelTrainingPackEnrolmentAction
{
    /**
     * Renvoie le montant à rembourser, en euros : ce qui est déjà rentré et
     * n'est plus dû. Ouvrir le remboursement revient à l'appelant, comme pour
     * {@see LeaveTrainingPackAction}.
     *
     * @throws \DomainException
     */
    public function __invoke(Subscription $subscription, TrainingPack $pack, int $familyMembersCount = 1): float
    {
        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        if ($pivot === null || ! in_array($pivot->status, ['enrolled', 'left'], true)) {
            throw new \DomainException(__('Only a validated training pack can be cancelled as an encoding error.'));
        }

        $exit = new TrainingPackExit;
        $marked = $exit->markedSessionsCount($subscription, $pack);

        if ($marked > 0) {
            throw new \DomainException(trans_choice(
                '{1}The coach marked this member at one session: they came, so this is a departure, not an encoding error.|[2,*]The coach marked this member at :count sessions: they came, so this is a departure, not an encoding error.',
                $marked,
                ['count' => $marked],
            ));
        }

        $amountDueBefore = (float) $subscription->amount_due;

        [$reduced, $refundable, $absences] = DB::transaction(function () use ($subscription, $pack, $familyMembersCount, $exit): array {
            $absences = $exit->eraseAbsences($subscription, $pack);

            $subscription->trainingPacks()->detach($pack->id);

            (new CalculatePriceAction)($subscription, $familyMembersCount);
            $subscription->refresh();

            $reduce = new ReduceOutstandingInvoiceAction;
            $reduced = $reduce->project($subscription, (float) $subscription->amount_due)['reduced'];
            $overpaid = $reduce($subscription);

            // netAmountPaid() et non totalPaid() : un remboursement déjà engagé
            // ne se rend pas deux fois — même raisonnement qu'un départ.
            return [$reduced, round(min($overpaid, $subscription->netAmountPaid()), 2), $absences];
        });

        // Une ligne déjà partie avait rendu sa place à son départ.
        if ($pivot->status === 'enrolled') {
            app(TrainingWaitlistService::class)->releaseSpot($pack);
        }

        activity()
            ->performedOn($subscription)
            ->causedBy(Auth::user())
            ->event('training_pack_enrolment_cancelled')
            ->withProperties([
                'training_pack_id' => $pack->id,
                'training_pack' => $pack->name,
                'status' => $pivot->status,
                'starts_on' => $pivot->starts_on,
                'ends_on' => $pivot->ends_on,
                'override_amount' => $pivot->override_amount,
                'absences_erased' => $absences,
                'amount_due_before' => $amountDueBefore,
                'amount_due_after' => (float) $subscription->amount_due,
                'claim_reduced' => $reduced,
                'refundable' => $refundable,
            ])
            ->log('training_pack_enrolment_cancelled');

        $subscription->user->notify(new TrainingPackEnrolmentCancelledNotification($pack, $subscription, $reduced, $refundable));

        return $refundable;
    }
}
