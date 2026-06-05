<?php

declare(strict_types=1);

namespace App\Contracts;

interface DescribesPayment
{
    /**
     * Full name of the person responsible for the payment.
     * Falls back to a dash when the payer can no longer be resolved.
     */
    public function getPayerName(): string;

    /**
     * Human-readable description of what the payment is for.
     *
     * @return array{type: string, name: string}
     */
    public function getPaymentLabel(): array;
}
