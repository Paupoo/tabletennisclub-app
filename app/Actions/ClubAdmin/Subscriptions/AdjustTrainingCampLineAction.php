<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use App\Domains\Trainings\Services\TrainingCampBilling;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Sets a member's own price on a stage: half the days, a single day.
 *
 * The stage counterpart of {@see ReconcileTrainingPackAction}, without the
 * dates — a stage has no prorata, so a partial attendance is a forced amount
 * and its reason. The stage's invoice follows: the unpaid request shrinks or
 * grows, and money already received beyond the new price leaves as a refund.
 */
final readonly class AdjustTrainingCampLineAction
{
    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * @param  float|null  $overrideAmount  In euros. `null` gives the line back the stage's price.
     * @return float the amount sent to refund, in euros
     *
     * @throws \DomainException
     */
    public function __invoke(Subscription $subscription, TrainingPack $camp, ?float $overrideAmount, ?string $overrideReason = null): float
    {
        $line = $this->billing->line($subscription, $camp);

        if ($line === null || ! $line->invoiced_separately) {
            throw new \DomainException(__('This member is not registered for this training pack.'));
        }

        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;

        if ($overrideAmount !== null && $overrideAmount < 0) {
            throw new \DomainException(__('A forced amount cannot be negative.'));
        }

        if ($overrideAmount !== null && $overrideReason === null) {
            throw new \DomainException(__('A reason is required to force the amount of a training pack.'));
        }

        $before = $line->getAmountDue();

        $refunded = DB::transaction(function () use ($line, $camp, $overrideAmount, $overrideReason): float {
            $line->forceFill([
                'override_amount' => $overrideAmount !== null ? (int) round($overrideAmount * 100) : null,
                'override_reason' => $overrideAmount !== null ? $overrideReason : null,
            ])->save();

            return $this->billing->sync($line, __('The :pack line has been adjusted downwards.', ['pack' => $camp->name]))['refunded'];
        });

        activity()
            ->performedOn($subscription)
            ->causedBy(Auth::user())
            ->event('training_camp_adjusted')
            ->withProperties([
                'training_pack_id' => $camp->id,
                'training_pack' => $camp->name,
                'amount_before' => $before,
                'amount_after' => $line->getAmountDue(),
                'override_reason' => $overrideAmount !== null ? $overrideReason : null,
                'refundable' => $refunded,
            ])
            ->log('training_camp_adjusted');

        return $refunded;
    }
}
