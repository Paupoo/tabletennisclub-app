<?php
declare(strict_types=1);

namespace App\Actions\Subscriptions;

use App\Models\Subscription;

final class CalculatePriceAction
{
    private float $recreativePrice = 60;
    private float $competitivePrice = 125;
    private float $trainingPrice = 90;
    private float $trainingDiscountedPrice = 80;

    public function __invoke(Subscription $subscription): Subscription
    {
        $subscription->subscription_price = $subscription->is_competitive ? $this->competitivePrice : $this->recreativePrice;
        $subscription->trainings_count = $subscription->trainingPacks()->count();
        $subscription->training_unit_price = $subscription->trainings_count > 1 ? $this->trainingDiscountedPrice : $this->trainingPrice;
        $subscription->amount_due = array_sum([
            $subscription->subscription_price,
            $subscription->trainings_count * $subscription->training_unit_price,
        ]);

        $subscription->save();

        return $subscription;
    }
}
