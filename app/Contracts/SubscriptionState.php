<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ClubAdmin\Subscription\Subscription;

interface SubscriptionState
{
    public function availableTransitions(): array;

    public function cancel(Subscription $subscription): void;

    public function canGeneratePayment(Subscription $subscription): bool;

    public function confirm(Subscription $subscription): void;

    public function getStatus(): string;

    public function markAsPaid(Subscription $subscription): void;

    public function refund(Subscription $subscription): void;

    public function unconfirm(Subscription $subscription): void;
}
