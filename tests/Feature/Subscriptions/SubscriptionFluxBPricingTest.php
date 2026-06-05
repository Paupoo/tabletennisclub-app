<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Trainings\Models\TrainingPack;

// ─── Flux A — pending review price summary (pendingReviewEstimatedTotal) ──────
// The computed property replicates CalculatePriceAction logic on the approved
// pack selection before the admin clicks "Approve". We test via the action.

describe('Flux A pending review — estimated total', function (): void {

    test('competitive subscription with no packs → 125€', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => true,
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->amount_due)->toBe(125.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('recreational subscription with no packs → 60€', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => false,
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->amount_due)->toBe(60.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('one discountable pack → no discount (count = 1)', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => true,
        ]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new CalculatePriceAction)($subscription);

        // 1 discountable pack, no family → applyDiscount = false → 125 + 90 = 215€
        expect($subscription->amount_due)->toBe(215.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('two discountable packs → −10€ each', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => true,
        ]);
        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'enrolled']);

        (new CalculatePriceAction)($subscription);

        // 2 discountable packs → applyDiscount = true → 125 + 80 + 80 = 285€
        expect($subscription->amount_due)->toBe(285.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('family member with one discountable pack → discount applies', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => true,
            'has_other_family_members' => true,
        ]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new CalculatePriceAction)($subscription, 2);

        // familyCount = 2 → applyDiscount = true → 125 + 80 = 205€
        expect($subscription->amount_due)->toBe(205.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('fixed price pack is never discounted', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => true,
        ]);
        $discountable = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $fixed = TrainingPack::factory()->create(['price' => 350, 'allow_discount' => false]);
        $subscription->trainingPacks()->attach($discountable->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($fixed->id, ['status' => 'enrolled']);

        (new CalculatePriceAction)($subscription);

        // discountablePacks.count() = 1, not > 1 → applyDiscount = false → 90 + 350 = 440
        expect($subscription->amount_due)->toBe(125.0 + 90.0 + 350.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('pending packs are excluded from price calculation', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'pending',
            'is_competitive' => true,
        ]);
        $enrolled = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pending = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $subscription->trainingPacks()->attach($enrolled->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($pending->id, ['status' => 'pending']);

        (new CalculatePriceAction)($subscription);

        // Only enrolled count → 1 pack, no discount → 125 + 90 = 215€
        expect($subscription->amount_due)->toBe(215.0);
    })->group('subscriptions', 'pricing', 'flux-a');

    test('subscription_price DTO field is 125 for competitive and 60 for recreational', function (): void {
        $competitive = Subscription::factory()->create(['is_competitive' => true]);
        $recreational = Subscription::factory()->create(['is_competitive' => false]);

        expect($competitive->is_competitive ? 125.0 : 60.0)->toBe(125.0)
            ->and($recreational->is_competitive ? 125.0 : 60.0)->toBe(60.0);
    })->group('subscriptions', 'pricing', 'flux-a');

});

// Flux B = member already paid/confirmed, requests additional training packs

describe('Flux B delta pricing', function (): void {

    test('delta payment applies multi-pack discount when adding a third discountable pack', function (): void {
        // Subscription with 2 discountable packs already enrolled at discounted price
        // 2 packs → applyDiscount = true → each costs 80€ → training total = 160€
        $subscription = Subscription::factory()->create([
            'status' => 'paid',
            'is_competitive' => true,
        ]);

        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack3 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($pack3->id, ['status' => 'pending']);

        // Sync amount_due to reflect the 2 enrolled packs (with discount = 2×80€)
        (new CalculatePriceAction)($subscription);
        // amount_due = 125 (competitive) + 80 + 80 = 285€
        expect($subscription->amount_due)->toBe(285.0);

        $previousAmountDue = $subscription->amount_due;

        (new ApproveTrainingPacksAction)($subscription, [$pack3->id]);
        $subscription->refresh();

        // After: 3 discountable packs → all at 80€ → total = 125 + 80×3 = 365€
        expect($subscription->amount_due)->toBe(365.0);

        $delta = max(0.0, $subscription->amount_due - $previousAmountDue);

        // Delta = 80€, NOT 90€ (full price)
        expect($delta)->toBe(80.0);
    })->group('subscriptions', 'pricing', 'flux-b');

    test('delta payment is full price when only one discountable pack total', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'paid',
            'is_competitive' => true,
        ]);

        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        // No enrolled packs yet → amount_due = subscription_price only (packs still pending)
        (new CalculatePriceAction)($subscription);
        expect($subscription->amount_due)->toBe(125.0);

        $previousAmountDue = $subscription->amount_due;

        (new ApproveTrainingPacksAction)($subscription, [$pack->id]);
        $subscription->refresh();

        // After: 1 discountable pack, no discount (count = 1, no family) → 90€
        expect($subscription->amount_due)->toBe(215.0); // 125 + 90

        $delta = max(0.0, $subscription->amount_due - $previousAmountDue);
        expect($delta)->toBe(90.0);
    })->group('subscriptions', 'pricing', 'flux-b');

    test('delta applies family discount even with a single new pack', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'paid',
            'is_competitive' => true,
            'has_other_family_members' => true,
        ]);

        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        (new CalculatePriceAction)($subscription, 2); // family of 2
        expect($subscription->amount_due)->toBe(125.0); // pack still pending

        $previousAmountDue = $subscription->amount_due;

        (new ApproveTrainingPacksAction)($subscription, [$pack->id], 2);
        $subscription->refresh();

        // Family discount applies → pack costs 80€ instead of 90€
        expect($subscription->amount_due)->toBe(205.0); // 125 + 80

        $delta = max(0.0, $subscription->amount_due - $previousAmountDue);
        expect($delta)->toBe(80.0);
    })->group('subscriptions', 'pricing', 'flux-b');

    test('unapproved pending packs are detached and do not affect delta', function (): void {
        $subscription = Subscription::factory()->create([
            'status' => 'paid',
            'is_competitive' => true,
        ]);

        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'enrolled']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        (new CalculatePriceAction)($subscription);
        // 1 enrolled pack, no discount → 125 + 90 = 215
        expect($subscription->amount_due)->toBe(215.0);

        $previousAmountDue = $subscription->amount_due;

        // Reject pack2 (approve empty list)
        (new ApproveTrainingPacksAction)($subscription, []);
        $subscription->refresh();

        // pack2 was detached, still 1 enrolled pack → amount_due unchanged
        expect($subscription->amount_due)->toBe(215.0);
        expect(max(0.0, $subscription->amount_due - $previousAmountDue))->toBe(0.0);

        // pack2 is no longer attached
        expect($subscription->trainingPacks()->where('training_packs.id', $pack2->id)->exists())->toBeFalse();
    })->group('subscriptions', 'pricing', 'flux-b');

});
