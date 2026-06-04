<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\ProcessPaymentAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('updates the payment record with transaction info and status', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);
    $payment = $subscription->payments()->create([
        'reference' => 'REF/2026/00001',
        'amount_due' => 8000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    (new ProcessPaymentAction)->execute($subscription, 'TXN-001', 80.00, 'paid');

    expect($payment->fresh()->transaction_id)->toBe('TXN-001');
    expect($payment->fresh()->amount_paid)->toBe(80.0);
    expect($payment->fresh()->status)->toBe('paid');
});

it('throws a DomainException when no pending payment exists', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);

    expect(fn (): mixed => (new ProcessPaymentAction)->execute($subscription, 'TXN-001', 80.00, 'paid'))
        ->toThrow(DomainException::class);
});

it('does not touch the subscription when status is failed', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);
    $subscription->payments()->create([
        'reference' => 'REF/2026/00002',
        'amount_due' => 8000,
        'amount_paid' => 0,
        'status' => 'pending',
    ]);

    (new ProcessPaymentAction)->execute($subscription, 'TXN-002', 0.00, 'failed');

    // Subscription status should remain unchanged for 'failed'
    expect($subscription->fresh()->status)->toBe('confirmed');
});
