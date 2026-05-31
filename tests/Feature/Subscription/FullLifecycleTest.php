<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Subscriptions\Notifications\SubscriptionRejectedNotification;
use App\Domains\Trainings\Models\TrainingPack;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use Illuminate\Support\Facades\Notification;

// ─── Flux A — Full registration + training, single payment ────────────────────

describe('Flux A — new affiliation with training packs, one payment', function () {

    test('user submits affiliation + 2 packs → all pending, price not yet calculated', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'pending',
            'amount_due' => 125,
        ]);

        $pack1 = TrainingPack::factory()->create(['price' => 90, 'max_participants' => 10]);
        $pack2 = TrainingPack::factory()->create(['price' => 80, 'max_participants' => 10]);

        (new EnrollInTrainingPackAction)($subscription, $pack1);
        (new EnrollInTrainingPackAction)($subscription, $pack2);

        $pivots = $subscription->trainingPacks()->get();
        expect($pivots)->toHaveCount(2)
            ->and($pivots->every(fn ($p) => $p->pivot->status === 'pending'))->toBeTrue();

        // Before admin approval: CalculatePriceAction counts only enrolled → only base price
        (new CalculatePriceAction)($subscription);
        expect($subscription->fresh()->amount_due)->toBe(125.0);
    })->group('lifecycle', 'flux-a');

    test('admin approves subscription + both packs → enrolled, price recalculated', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'pending',
            'amount_due' => 125,
        ]);

        $pack1 = TrainingPack::factory()->create(['price' => 90, 'max_participants' => 10, 'allow_discount' => false]);
        $pack2 = TrainingPack::factory()->create(['price' => 80, 'max_participants' => 10, 'allow_discount' => false]);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        // Admin confirms the subscription then approves both packs
        $subscription->confirm();
        (new ApproveTrainingPacksAction)($subscription, [$pack1->id, $pack2->id]);

        $subscription->refresh();
        expect($subscription->status)->toBe('confirmed')
            ->and($subscription->amount_due)->toBe(125.0 + 90.0 + 80.0);

        $pivots = $subscription->trainingPacks()->get();
        expect($pivots->every(fn ($p) => $p->pivot->status === 'enrolled'))->toBeTrue();
    })->group('lifecycle', 'flux-a');

    test('admin approves only one pack → second detached, price reflects approved pack only', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'pending',
            'amount_due' => 125,
        ]);

        $pack1 = TrainingPack::factory()->create(['price' => 90, 'max_participants' => 10, 'allow_discount' => false]);
        $pack2 = TrainingPack::factory()->create(['price' => 80, 'max_participants' => 10, 'allow_discount' => false]);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        $subscription->confirm();
        (new ApproveTrainingPacksAction)($subscription, [$pack1->id]); // only approve pack1

        $subscription->refresh();
        expect($subscription->amount_due)->toBe(125.0 + 90.0);
        expect($subscription->trainingPacks()->where('training_pack_id', $pack2->id)->exists())->toBeFalse();
    })->group('lifecycle', 'flux-a');

    test('payment generated after approval covers full price', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'confirmed',
            'amount_due' => 295,
        ]);

        $payment = $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $subscription->getAmountDue(),
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        expect($payment->amount_due)->toBe(295.0)
            ->and($subscription->payments()->where('status', 'pending')->count())->toBe(1);
    })->group('lifecycle', 'flux-a');

})->group('lifecycle');

// ─── Flux A — Two-part payment by bank transfer ────────────────────────────────

describe('Flux A — user pays affiliation + training in two separate bank transfers', function () {

    /**
     * Scenario: Alice registers (competitive, 125€) + 2 training packs (90€ + 80€).
     * Admin approves everything. One payment of 295€ is generated.
     *
     * Alice sends 2 separate bank transfers:
     *   - Transfer 1: 125€ (affiliation only, wrong/partial amount)
     *   - Transfer 2: 170€ (training packs)
     *
     * The treasurer imports transfers one by one.
     * After both are reconciled the subscription is marked paid.
     */
    test('two successive imports reconcile a split payment and mark the subscription paid', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'confirmed',
            'amount_due' => 295,
            'amount_paid' => 0,
        ]);

        $ref = (new GeneratePaymentReference)();
        $payment = $subscription->payments()->create([
            'reference' => $ref,
            'amount_due' => 295,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // ── Import 1: 125€ from Alice ─────────────────────────────────────────
        $tx1 = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 125.0,
            'counterparty_name' => $user->first_name . ' ' . $user->last_name,
            'structured_reference' => $ref,
            'description' => 'Affiliation',
        ]);

        // Partial reconciliation: amount doesn't fully cover the payment
        // Treasurer reconciles manually, partial amount registered
        $amountPaidSoFar = (float) $tx1->amount;
        $payment->update(['amount_paid' => $amountPaidSoFar]);
        // Payment still pending (not fully paid)
        $subscription->update(['amount_paid' => $amountPaidSoFar]);
        expect($payment->fresh()->status)->toBe('pending')
            ->and($subscription->fresh()->status)->toBe('confirmed');

        // ── Import 2: 170€ from Alice ─────────────────────────────────────────
        $tx2 = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 170.0,
            'counterparty_name' => $user->first_name . ' ' . $user->last_name,
            'structured_reference' => $ref,
            'description' => 'Entraînements',
        ]);

        $totalPaid = $amountPaidSoFar + (float) $tx2->amount; // 295.0
        $payment->update([
            'transaction_id' => $tx2->id,
            'amount_paid' => $totalPaid,
            'status' => 'paid',
        ]);
        $subscription->update(['amount_paid' => $totalPaid]);
        $subscription->markAsPaid();

        expect($payment->fresh()->status)->toBe('paid')
            ->and($payment->fresh()->amount_paid)->toBe(295.0)
            ->and($subscription->fresh()->status)->toBe('paid')
            ->and($subscription->fresh()->amount_paid)->toBe(295.0);
    })->group('lifecycle', 'flux-a', 'payments');

    test('first import partial, second import completes — subscription stays confirmed until fully paid', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => false,
            'status' => 'confirmed',
            'amount_due' => 60,
            'amount_paid' => 0,
        ]);

        $ref = (new GeneratePaymentReference)();
        $payment = $subscription->payments()->create([
            'reference' => $ref,
            'amount_due' => 60,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // Only 30€ received first
        $payment->update(['amount_paid' => 30.0]);
        $subscription->update(['amount_paid' => 30.0]);

        expect($subscription->fresh()->status)->toBe('confirmed'); // not yet paid

        // Second transfer: remaining 30€
        $payment->update(['amount_paid' => 60.0, 'status' => 'paid']);
        $subscription->update(['amount_paid' => 60.0]);
        $subscription->markAsPaid();

        expect($subscription->fresh()->status)->toBe('paid');
    })->group('lifecycle', 'flux-a', 'payments');

})->group('lifecycle');

// ─── Flux B — Mid-season training pack added by a paid member ─────────────────

describe('Flux B — paid member requests additional training pack mid-season', function () {

    test('paid member requests training pack → pending, price unchanged', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'paid',
            'amount_due' => 125,
            'amount_paid' => 125,
        ]);

        $newPack = TrainingPack::factory()->create(['price' => 90, 'max_participants' => 10, 'allow_discount' => false]);

        $status = (new EnrollInTrainingPackAction)($subscription, $newPack);

        expect($status)->toBe('pending');

        // Price unchanged — pending packs excluded from CalculatePriceAction
        (new CalculatePriceAction)($subscription);
        expect($subscription->fresh()->amount_due)->toBe(125.0);
    })->group('lifecycle', 'flux-b');

    test('admin approves new pack → enrolled, supplementary payment created for pack cost only', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'paid',
            'amount_due' => 125,
            'amount_paid' => 125,
        ]);

        // Pre-existing enrolled pack from original registration
        $existingPack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => false]);
        $subscription->trainingPacks()->attach($existingPack->id, ['status' => 'enrolled']);

        // Mid-season request
        $newPack = TrainingPack::factory()->create(['price' => 80, 'max_participants' => 10, 'allow_discount' => false]);
        $subscription->trainingPacks()->attach($newPack->id, ['status' => 'pending']);

        (new ApproveTrainingPacksAction)($subscription, [$newPack->id]);

        $subscription->refresh();

        // Existing pack stays enrolled; new pack is now enrolled
        $pivot = $subscription->trainingPacks()->where('training_pack_id', $newPack->id)->first();
        expect($pivot->pivot->status)->toBe('enrolled');

        // Supplementary payment: only the new pack price (80€)
        $newlyEnrolledCost = $subscription->trainingPacks()
            ->whereIn('training_pack_id', [$newPack->id])
            ->wherePivot('status', 'enrolled')
            ->get()
            ->sum(fn ($p) => (float) $p->price);

        expect($newlyEnrolledCost)->toBe(80.0);

        $payment = $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => $newlyEnrolledCost,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        expect($payment->amount_due)->toBe(80.0);
    })->group('lifecycle', 'flux-b');

    test('treasurer reconciles the supplementary payment independently of the original', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'paid',
            'amount_due' => 125,
            'amount_paid' => 125,
        ]);

        $ref1 = (new GeneratePaymentReference)();
        $paidPayment = $subscription->payments()->create([
            'reference' => $ref1,
            'amount_due' => 125,
            'amount_paid' => 125,
            'status' => 'paid',
        ]);

        $ref2 = (new GeneratePaymentReference)();
        $pendingPayment = $subscription->payments()->create([
            'reference' => $ref2,
            'amount_due' => 80,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        expect($subscription->payments()->where('status', 'pending')->count())->toBe(1)
            ->and($subscription->payments()->where('status', 'paid')->count())->toBe(1);

        // Treasurer imports bank file — finds tx matching ref2
        $tx = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 80.0,
            'counterparty_name' => $user->first_name,
            'structured_reference' => $ref2,
            'description' => 'Pack supplémentaire',
        ]);

        $pendingPayment->update([
            'transaction_id' => $tx->id,
            'amount_paid' => $tx->amount,
            'status' => 'paid',
        ]);

        expect($pendingPayment->fresh()->status)->toBe('paid')
            ->and($paidPayment->fresh()->status)->toBe('paid') // original unchanged
            ->and($subscription->payments()->where('status', 'pending')->count())->toBe(0);
    })->group('lifecycle', 'flux-b', 'payments');

})->group('lifecycle');

// ─── Re-affiliation after admin rejection ─────────────────────────────────────

describe('re-affiliation after admin rejection', function () {

    test('rejected subscription is cancelled and user can create a new pending subscription', function () {
        Notification::fake();

        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $first = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'pending',
        ]);
        $first->load(['user', 'season']);

        // Admin rejects
        $user->notify(new SubscriptionRejectedNotification($first, 'Niveau trop élevé.', 'level'));
        $first->cancel();

        expect($first->fresh()->status)->toBe('cancelled');
        Notification::assertSentTo($user, SubscriptionRejectedNotification::class);

        // User re-affiliates (new subscription)
        $second = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'pending',
        ]);

        expect($second->fresh()->status)->toBe('pending')
            ->and($second->id)->not->toBe($first->id);

        // Both exist — one cancelled, one pending
        $userSubs = Subscription::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->get();
        expect($userSubs)->toHaveCount(2)
            ->and($userSubs->firstWhere('status', 'cancelled'))->not->toBeNull()
            ->and($userSubs->firstWhere('status', 'pending'))->not->toBeNull();
    })->group('lifecycle', 'rejection', 're-affiliation');

    test('second subscription can be independently approved and paid', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true]);

        // Cancelled first subscription
        Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'status' => 'cancelled',
        ]);

        // Fresh pending subscription
        $second = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => false,
            'status' => 'pending',
            'amount_due' => 60,
        ]);

        $second->confirm();

        $ref = (new GeneratePaymentReference)();
        $payment = $second->payments()->create([
            'reference' => $ref,
            'amount_due' => 60,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $tx = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 60.0,
            'counterparty_name' => $user->first_name,
            'structured_reference' => $ref,
            'description' => 'Re-affiliation',
        ]);

        $payment->update([
            'transaction_id' => $tx->id,
            'amount_paid' => $tx->amount,
            'status' => 'paid',
        ]);
        $second->update(['amount_paid' => 60.0]);
        $second->markAsPaid();

        expect($second->fresh()->status)->toBe('paid');
    })->group('lifecycle', 'rejection', 're-affiliation');

})->group('lifecycle');

// ─── Complex: affiliate then add training + 2 successive treasurer imports ─────

describe('complex — affiliate, add training mid-season, two successive import batches', function () {

    /**
     * Full story:
     * 1. Bob affiliates (competitive, 125€). Admin approves. Payment A (125€) created.
     * 2. Treasurer import batch 1: Bob's 125€ transfer arrives → Payment A reconciled → subscription = paid.
     * 3. Bob requests a training pack (Flux B, 90€). Admin approves. Payment B (90€) created.
     * 4. Treasurer import batch 2: Bob's 90€ transfer arrives → Payment B reconciled.
     * 5. Both payments paid; subscription remains paid.
     */
    test('affiliate + pay + add training + pay training via 2 successive import batches', function () {
        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        // ── Step 1: Affiliation confirmed by admin ────────────────────────────
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'confirmed',
            'amount_due' => 125,
            'amount_paid' => 0,
        ]);

        $refA = (new GeneratePaymentReference)();
        $paymentA = $subscription->payments()->create([
            'reference' => $refA,
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // ── Step 2: Treasurer batch 1 — reconcile affiliation payment ─────────
        $txA = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 125.0,
            'counterparty_name' => $user->first_name,
            'structured_reference' => $refA,
            'description' => 'Affiliation',
        ]);

        $paymentA->update([
            'transaction_id' => $txA->id,
            'amount_paid' => $txA->amount,
            'status' => 'paid',
        ]);
        $subscription->update(['amount_paid' => 125.0]);
        $subscription->markAsPaid();

        expect($subscription->fresh()->status)->toBe('paid')
            ->and($paymentA->fresh()->status)->toBe('paid');

        // ── Step 3: Bob requests a training pack mid-season (Flux B) ──────────
        $pack = TrainingPack::factory()->create(['price' => 90, 'max_participants' => 10, 'allow_discount' => false]);
        (new EnrollInTrainingPackAction)($subscription->fresh(), $pack);

        // Admin approves the pending pack
        (new ApproveTrainingPacksAction)($subscription->fresh(), [$pack->id]);

        $refB = (new GeneratePaymentReference)();
        $paymentB = $subscription->payments()->create([
            'reference' => $refB,
            'amount_due' => 90,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // Two pending payments now: 0 (paymentA is paid) + 1 (paymentB is pending)
        expect($subscription->payments()->where('status', 'pending')->count())->toBe(1)
            ->and($subscription->payments()->where('status', 'paid')->count())->toBe(1);

        // ── Step 4: Treasurer batch 2 — reconcile training payment ───────────
        $txB = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 90.0,
            'counterparty_name' => $user->first_name,
            'structured_reference' => $refB,
            'description' => 'Entraînement Pack',
        ]);

        $paymentB->update([
            'transaction_id' => $txB->id,
            'amount_paid' => $txB->amount,
            'status' => 'paid',
        ]);

        // ── Step 5: Assert final state ────────────────────────────────────────
        expect($paymentB->fresh()->status)->toBe('paid')
            ->and($subscription->payments()->where('status', 'pending')->count())->toBe(0)
            ->and($subscription->payments()->where('status', 'paid')->count())->toBe(2)
            ->and($subscription->fresh()->status)->toBe('paid'); // subscription stays paid

        $enrolledPacks = $subscription->trainingPacks()->wherePivot('status', 'enrolled')->get();
        expect($enrolledPacks)->toHaveCount(1)
            ->and($enrolledPacks->first()->id)->toBe($pack->id);
    })->group('lifecycle', 'complex', 'payments');

    test('batch auto-reconciliation matches each payment to the correct transaction by reference', function () {
        $normalize = fn (string $ref): string => preg_replace('/[^0-9]/', '', $ref) ?? '';

        $season = Season::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'season_id' => $season->id,
            'is_competitive' => true,
            'status' => 'confirmed',
            'amount_due' => 125,
        ]);

        $refA = (new GeneratePaymentReference)();
        $paymentA = $subscription->payments()->create([
            'reference' => $refA,
            'amount_due' => 125,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        $refB = (new GeneratePaymentReference)();
        $paymentB = $subscription->payments()->create([
            'reference' => $refB,
            'amount_due' => 90,
            'amount_paid' => 0,
            'status' => 'pending',
        ]);

        // Two transactions arrive in the same batch (different days, same import)
        $txA = Transaction::create([
            'date' => now()->subDay()->toDateString(),
            'amount' => 125.0,
            'counterparty_name' => $user->first_name,
            'structured_reference' => $refA,
            'description' => 'Affiliation',
        ]);

        $txB = Transaction::create([
            'date' => now()->toDateString(),
            'amount' => 90.0,
            'counterparty_name' => $user->first_name,
            'structured_reference' => $refB,
            'description' => 'Formation',
        ]);

        // Simulate auto-reconciliation engine
        $unreconciledTxs = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->get()
            ->keyBy(fn ($t) => $normalize($t->structured_reference ?? '___' . $t->id));

        $pendingPayments = Payment::where('status', 'pending')->whereNull('transaction_id')->get();

        $matches = [];
        foreach ($pendingPayments as $p) {
            $normalizedRef = $normalize($p->reference);
            if (! $normalizedRef) {
                continue;
            }
            $tx = $unreconciledTxs->get($normalizedRef);
            if ($tx && abs($tx->amount - $p->amount_due) < 0.01) {
                $matches[] = ['payment_id' => $p->id, 'transaction_id' => $tx->id];
                $unreconciledTxs->forget($normalizedRef);
            }
        }

        expect($matches)->toHaveCount(2);

        $matchedPaymentIds = collect($matches)->pluck('payment_id')->sort()->values()->toArray();
        expect($matchedPaymentIds)->toContain($paymentA->id)
            ->and($matchedPaymentIds)->toContain($paymentB->id);

        // Apply matches
        foreach ($matches as $match) {
            $p = Payment::find($match['payment_id']);
            $tx = Transaction::find($match['transaction_id']);
            $p->update([
                'transaction_id' => $tx->id,
                'amount_paid' => $tx->amount,
                'status' => 'paid',
            ]);
        }

        expect($paymentA->fresh()->status)->toBe('paid')
            ->and($paymentB->fresh()->status)->toBe('paid');
    })->group('lifecycle', 'complex', 'payments');

})->group('lifecycle');
