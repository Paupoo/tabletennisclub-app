<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\ExternalParticipants;

use App\Domains\ClubAdmin\ExternalParticipants\Models\ExternalRegistration;
use App\Domains\Trainings\Services\TrainingCampBilling;
use Illuminate\Support\Facades\DB;

/**
 * Sets a non-member's own price on a stage, as for a member's line.
 *
 * The invoice follows: the unpaid request shrinks or grows, and money already
 * received beyond the new price leaves as a refund.
 */
final readonly class AdjustExternalRegistrationAction
{
    public function __construct(private TrainingCampBilling $billing = new TrainingCampBilling) {}

    /**
     * @param  float|null  $overrideAmount  In euros. `null` gives back the stage's price.
     * @return float the amount sent to refund, in euros
     *
     * @throws \DomainException
     */
    public function __invoke(ExternalRegistration $registration, ?float $overrideAmount, ?string $overrideReason = null): float
    {
        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;

        if ($overrideAmount !== null && $overrideAmount < 0) {
            throw new \DomainException(__('A forced amount cannot be negative.'));
        }

        if ($overrideAmount !== null && $overrideReason === null) {
            throw new \DomainException(__('A reason is required to force the amount of a training pack.'));
        }

        return DB::transaction(function () use ($registration, $overrideAmount, $overrideReason): float {
            $registration->update([
                'override_amount' => $overrideAmount !== null ? (int) round($overrideAmount * 100) : null,
                'override_reason' => $overrideAmount !== null ? $overrideReason : null,
            ]);

            return $this->billing->sync($registration, __('The :pack line has been adjusted downwards.', [
                'pack' => $registration->registrable->name,
            ]))['refunded'];
        });
    }
}
