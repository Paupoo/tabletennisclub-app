<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;

final class CalculatePriceAction
{
    private const COMPETITIVE_PRICE = 125.0;

    private const DISCOUNT_AMOUNT = 10.0; // €

    private const RECREATIVE_PRICE = 60.0;

    public function __invoke(Subscription $subscription, int $familyMembersCount = 1): Subscription
    {
        // Affiliation price
        $subscription->subscription_price = $subscription->is_competitive
            ? self::COMPETITIVE_PRICE
            : self::RECREATIVE_PRICE;

        // Training packs: only enrolled (not waitlisted)
        $enrolledPacks = $subscription->trainingPacks()
            ->wherePivot('status', 'enrolled')
            ->get();

        $discountablePacks = $enrolledPacks->filter(fn (TrainingPack $p) => $p->allow_discount);
        $fixedPacks = $enrolledPacks->filter(fn (TrainingPack $p) => ! $p->allow_discount);

        $applyDiscount = $discountablePacks->count() > 1 || $familyMembersCount > 1;

        $trainingTotal = 0.0;

        foreach ($discountablePacks as $pack) {
            $trainingTotal += $applyDiscount
                ? max(0.0, (float) $pack->price - self::DISCOUNT_AMOUNT)
                : (float) $pack->price;
        }

        foreach ($fixedPacks as $pack) {
            $trainingTotal += (float) $pack->price;
        }

        $subscription->trainings_count = $enrolledPacks->count();
        $subscription->training_unit_price = $enrolledPacks->isEmpty()
            ? 0.0
            : round($trainingTotal / $enrolledPacks->count(), 2);

        // amount_due accessor expects euros, stores as cents internally
        $subscription->amount_due = (float) $subscription->subscription_price + $trainingTotal;

        $subscription->save();

        return $subscription;
    }
}
