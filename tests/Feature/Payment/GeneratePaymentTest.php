<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePayment;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Http\RedirectResponse;

uses()->group('payment');

beforeEach(function (): void {
    $this->actingAs(User::factory()->isAdmin()->create());
});

it('creates a payment for a subscription', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);
    $action = new GeneratePayment;

    $action($subscription);

    expect($subscription->payments()->count())->toBe(1);
});

it('creates a payment with correct attributes', function (): void {
    $subscription = Subscription::factory()->create([
        'status' => 'confirmed',
        'amount_due' => 50,
    ]);
    $action = new GeneratePayment;

    $action($subscription);

    $payment = $subscription->payments()->first();

    expect($payment)
        ->reference->not->toBeNull()
        ->amount_due->toBe(50.00)
        ->amount_paid->toBe(0.0)
        ->status->toBe('pending');
});

it('generates unique payment references', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);
    $action = new GeneratePayment;

    $action($subscription);
    $action($subscription);

    $references = $subscription->payments()->pluck('reference')->toArray();

    expect($references)->toHaveCount(2)
        ->and($references[0])->not->toBe($references[1]);
});

it('redirects back with success message', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);
    $action = new GeneratePayment;

    $response = $action($subscription);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect(session('success'))->toBe(__('A new payment has been generated'));
});

it('can generate multiple payments for the same subscription', function (): void {
    $subscription = Subscription::factory()->create(['status' => 'confirmed']);
    $action = new GeneratePayment;

    $action($subscription);
    $action($subscription);
    $action($subscription);

    expect($subscription->payments()->count())->toBe(3);

    $allPending = $subscription->payments()
        ->where('status', 'pending')
        ->count();

    expect($allPending)->toBe(3);
});
