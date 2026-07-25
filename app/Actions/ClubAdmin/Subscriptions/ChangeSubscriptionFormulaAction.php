<?php

declare(strict_types=1);

namespace App\Actions\ClubAdmin\Subscriptions;

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Observers\SubscriptionObserver;

class ChangeSubscriptionFormulaAction
{
    /**
     * Flips an affiliation between the recreative and the competitive formula
     * and reprices it, returning how much the change moves in euros: positive
     * when the member still owes a complement, negative when the club owes them
     * money back, zero when nothing has to be settled.
     *
     * The flip goes through the model rather than a query builder update, so
     * that {@see SubscriptionObserver} sees it and rebuilds the
     * force lists — a member leaving the competition has to leave them too.
     */
    public function __invoke(Subscription $subscription, int $familyMembersCount = 1): float
    {
        $amountDueBefore = (float) $subscription->amount_due;

        $subscription->is_competitive = ! $subscription->is_competitive;
        $subscription->save();

        (new CalculatePriceAction)($subscription, $familyMembersCount);
        $subscription->refresh();

        return round((float) $subscription->amount_due - $amountDueBefore, 2);
    }
}
