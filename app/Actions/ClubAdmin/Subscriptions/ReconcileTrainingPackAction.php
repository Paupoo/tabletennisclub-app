<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Réconciliation manuelle d'une ligne d'entraînement par le trésorier.
 *
 * Deux niveaux, du plus doux au plus brutal : corriger la période réellement
 * couverte — le prix se recalcule tout seul au pro rata — puis, si le calcul
 * ne sait toujours pas décrire le cas, forcer le montant. Le montant forcé
 * exige un motif : une ligne dont le prix ne s'explique ni par le barème ni
 * par une phrase n'est pas défendable devant un membre.
 */
class ReconcileTrainingPackAction
{
    /**
     * @param  float|null  $overrideAmount  En euros. `null` rend la main au calcul.
     *
     * @throws \DomainException
     */
    public function __invoke(
        Subscription $subscription,
        TrainingPack $pack,
        ?string $startsOn = null,
        ?string $endsOn = null,
        ?float $overrideAmount = null,
        ?string $overrideReason = null,
        int $familyMembersCount = 1,
    ): Subscription {
        $pivot = DB::table('subscription_training_pack')
            ->where('subscription_id', $subscription->id)
            ->where('training_pack_id', $pack->id)
            ->first();

        if (! $pivot) {
            throw new \DomainException(__('This member is not registered for this training pack.'));
        }

        $startsOn = $this->normaliseDate($startsOn);
        $endsOn = $this->normaliseDate($endsOn);
        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;

        if ($startsOn !== null && $endsOn !== null && $endsOn < $startsOn) {
            throw new \DomainException(__('The end date cannot precede the start date.'));
        }

        if ($overrideAmount !== null && $overrideAmount < 0) {
            throw new \DomainException(__('A forced amount cannot be negative.'));
        }

        if ($overrideAmount !== null && $overrideReason === null) {
            throw new \DomainException(__('A reason is required to force the amount of a training pack.'));
        }

        $before = (float) $subscription->amount_due;

        $subscription->trainingPacks()->updateExistingPivot($pack->id, [
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'override_amount' => $overrideAmount !== null ? (int) round($overrideAmount * 100) : null,
            // Le motif ne survit pas au montant : sans montant forcé il ne
            // décrirait plus rien.
            'override_reason' => $overrideAmount !== null ? $overrideReason : null,
        ]);

        (new CalculatePriceAction)($subscription, $familyMembersCount);
        $subscription->refresh();

        activity()
            ->performedOn($subscription)
            ->causedBy(Auth::user())
            ->event('training_pack_reconciled')
            ->withProperties([
                'training_pack_id' => $pack->id,
                'training_pack' => $pack->name,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'override_amount' => $overrideAmount,
                'override_reason' => $overrideAmount !== null ? $overrideReason : null,
                'amount_due_before' => $before,
                'amount_due_after' => (float) $subscription->amount_due,
            ])
            ->log('training_pack_reconciled');

        return $subscription;
    }

    private function normaliseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }
}
