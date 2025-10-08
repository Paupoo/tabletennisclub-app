<?php

namespace App\Contracts;

use App\Models\Subscription;

interface SubscriptionState
{
    public function unconfirm(Subscription $subscription): void;
    public function confirm(Subscription $subscription): void;
    public function markAsPaid(Subscription $subscription): void;
    public function refund(Subscription $subscription): void;
    public function cancel(Subscription $subscription): void;
    public function getStatus(): string;

}
