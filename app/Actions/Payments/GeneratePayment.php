<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;

class GeneratePayment
{
    /**
     * Generate a payment.
     */
    public function __invoke(Subscription $subscription): RedirectResponse
    {
        $referenceGenerator = new GeneratePaymentReference;

        $subscription->payments()->create([
            'reference' => $referenceGenerator(),
            'amount_due' => $subscription->getAmountDue(),
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        return back()
            ->with([
                'success' => __('A new payment has been generated'),
            ]);
    }
}
