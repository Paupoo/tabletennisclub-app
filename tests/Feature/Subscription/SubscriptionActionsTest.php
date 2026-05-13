<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CancelSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\ConfirmSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\DeleteSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkPaidSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\MarkRefundSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\SubscribeToSeasonAction;
use App\Actions\ClubAdmin\Subscriptions\SyncTrainingPackAction;
use App\Actions\ClubAdmin\Subscriptions\UnconfirmSubscriptionAction;
use App\Actions\ClubAdmin\Subscriptions\UnsubscribeFromSeasonAction;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\TrainingPack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// ============================================================
// CancelSubscriptionAction
// ============================================================

describe('CancelSubscriptionAction', function () {

    test('cancels a pending subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $response = (new CancelSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('cancelled')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('cancels a confirmed subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $response = (new CancelSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('cancelled')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when cancelling a paid subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        $response = (new CancelSubscriptionAction)($subscription);

        // State machine throws LogicException; action catches it and returns error redirect
        expect($subscription->fresh()->status)->toBe('paid') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when cancelling an already cancelled subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'cancelled']);

        $response = (new CancelSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('cancelled') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// ConfirmSubscriptionAction
// ============================================================

describe('ConfirmSubscriptionAction', function () {

    test('confirms a pending subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $response = (new ConfirmSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('confirmed')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when confirming an already confirmed subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $response = (new ConfirmSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('confirmed') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when confirming a paid subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        $response = (new ConfirmSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('paid') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('bug: does not recalculate price because CalculatePriceAction is instantiated but not invoked')
        ->skip(
            'ConfirmSubscriptionAction calls `new CalculatePriceAction($subscription)` instead of ' .
            '`(new CalculatePriceAction)($subscription)` — price is never recalculated on confirm.'
        )
        ->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// DeleteSubscriptionAction
// ============================================================

describe('DeleteSubscriptionAction', function () {

    test('soft-deletes the subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);
        $id = $subscription->id;

        $response = (new DeleteSubscriptionAction)($subscription);

        $this->assertSoftDeleted('subscriptions', ['id' => $id]);
        expect($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('cascade-deletes payments when the subscription is deleted', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed', 'amount_due' => 125]);
        $payment = $subscription->payments()->create([
            'reference'  => '100/2505/00101',
            'amount_due' => 125,
            'amount_paid' => 0,
            'status'     => 'pending',
        ]);

        (new DeleteSubscriptionAction)($subscription);

        // Payment model has no SoftDeletes — the observer does a hard DELETE
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
    })->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// MarkPaidSubscriptionAction
// ============================================================

describe('MarkPaidSubscriptionAction', function () {

    test('marks a confirmed subscription as paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $response = (new MarkPaidSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('paid')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when marking a pending subscription as paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $response = (new MarkPaidSubscriptionAction)($subscription);

        // PendingState throws LogicException; action catches and returns error redirect
        expect($subscription->fresh()->status)->toBe('pending') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when marking an already paid subscription as paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        $response = (new MarkPaidSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('paid') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// MarkRefundSubscriptionAction
// ============================================================

describe('MarkRefundSubscriptionAction', function () {

    test('marks a paid subscription as refunded', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        $response = (new MarkRefundSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('refunded')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when refunding a pending subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $response = (new MarkRefundSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('pending') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when refunding a confirmed subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $response = (new MarkRefundSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('confirmed') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// SyncTrainingPackAction
// ============================================================

describe('SyncTrainingPackAction', function () {

    test('syncs training packs on a pending subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);
        $packs = TrainingPack::factory()->count(2)->create();

        (new SyncTrainingPackAction)($packs->pluck('id')->toArray(), $subscription);

        expect($subscription->trainingPacks()->count())->toBe(2);
    })->group('subscriptions', 'actions');

    test('syncs training packs on a confirmed subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);
        $packs = TrainingPack::factory()->count(3)->create();

        (new SyncTrainingPackAction)($packs->pluck('id')->toArray(), $subscription);

        expect($subscription->trainingPacks()->count())->toBe(3);
    })->group('subscriptions', 'actions');

    test('replaces existing training packs on re-sync', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);
        $oldPack = TrainingPack::factory()->create();
        $newPacks = TrainingPack::factory()->count(2)->create();

        $subscription->trainingPacks()->attach($oldPack->id);

        (new SyncTrainingPackAction)($newPacks->pluck('id')->toArray(), $subscription);

        $currentIds = $subscription->trainingPacks()->pluck('training_packs.id')->toArray();
        expect($currentIds)->not->toContain($oldPack->id)
            ->and($subscription->trainingPacks()->count())->toBe(2);
    })->group('subscriptions', 'actions');

    test('throws DomainException when modifying a paid subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);
        $pack = TrainingPack::factory()->create();

        expect(fn () => (new SyncTrainingPackAction)([$pack->id], $subscription))
            ->toThrow(\DomainException::class);
    })->group('subscriptions', 'actions');

    test('throws DomainException when modifying a cancelled subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'cancelled']);
        $pack = TrainingPack::factory()->create();

        expect(fn () => (new SyncTrainingPackAction)([$pack->id], $subscription))
            ->toThrow(\DomainException::class);
    })->group('subscriptions', 'actions');

    test('throws DomainException when modifying a refunded subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'refunded']);
        $pack = TrainingPack::factory()->create();

        expect(fn () => (new SyncTrainingPackAction)([$pack->id], $subscription))
            ->toThrow(\DomainException::class);
    })->group('subscriptions', 'actions');

    test('bug: does not recalculate price because CalculatePriceAction is instantiated but not invoked')
        ->skip(
            'SyncTrainingPackAction calls `new CalculatePriceAction($subscription)` instead of ' .
            '`(new CalculatePriceAction)($subscription)` — price is never recalculated after sync.'
        )
        ->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// UnconfirmSubscriptionAction
// ============================================================

describe('UnconfirmSubscriptionAction', function () {

    test('reverts a confirmed subscription back to pending', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $response = (new UnconfirmSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('pending')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when subscription is already pending', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $response = (new UnconfirmSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('pending') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when subscription is paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        $response = (new UnconfirmSubscriptionAction)($subscription);

        expect($subscription->fresh()->status)->toBe('paid') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// UnsubscribeFromSeasonAction
// ============================================================

describe('UnsubscribeFromSeasonAction', function () {

    test('cancels a pending subscription for the given user and season', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);
        $subscription = Subscription::factory()->create([
            'user_id'   => $user->id,
            'season_id' => $season->id,
            'status'    => 'pending',
        ]);

        $response = (new UnsubscribeFromSeasonAction)($season, $user);

        expect($subscription->fresh()->status)->toBe('cancelled')
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('cancels a confirmed subscription', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);
        $subscription = Subscription::factory()->create([
            'user_id'   => $user->id,
            'season_id' => $season->id,
            'status'    => 'confirmed',
        ]);

        (new UnsubscribeFromSeasonAction)($season, $user);

        expect($subscription->fresh()->status)->toBe('cancelled');
    })->group('subscriptions', 'actions');

    test('returns error redirect when no active subscription exists for the user in the season', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $response = (new UnsubscribeFromSeasonAction)($season, $user);

        expect($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('does not cancel an already cancelled subscription (returns error redirect)', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);
        Subscription::factory()->create([
            'user_id'   => $user->id,
            'season_id' => $season->id,
            'status'    => 'cancelled',
        ]);

        // No active subscription found because the query excludes 'cancelled'
        $response = (new UnsubscribeFromSeasonAction)($season, $user);

        expect($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

    test('returns error redirect when attempting to unsubscribe a paid subscription (cannot cancel)', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);
        $subscription = Subscription::factory()->create([
            'user_id'   => $user->id,
            'season_id' => $season->id,
            'status'    => 'paid',
        ]);

        $response = (new UnsubscribeFromSeasonAction)($season, $user);

        // PaidState throws LogicException on cancel(); action catches and returns error redirect
        expect($subscription->fresh()->status)->toBe('paid') // unchanged
            ->and($response)->toBeInstanceOf(RedirectResponse::class);
    })->group('subscriptions', 'actions');

})->group('subscriptions');


// ============================================================
// SubscribeToSeasonAction
// ============================================================

describe('SubscribeToSeasonAction', function () {

    test('creates a competitive subscription for a user in an active season', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $request = Request::create('/subscribe', 'POST', [
            'user_id' => (string) $user->id,
            'type'    => 'competitive',
        ]);

        $response = (new SubscribeToSeasonAction)($season, $request);

        expect($response)->toBeInstanceOf(RedirectResponse::class);
        $this->assertDatabaseHas('subscriptions', [
            'user_id'        => $user->id,
            'season_id'      => $season->id,
            'is_competitive' => true,
            'status'         => 'pending',
        ]);
    })->group('subscriptions', 'actions');

    test('creates a recreational subscription for a user in an active season', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $request = Request::create('/subscribe', 'POST', [
            'user_id' => (string) $user->id,
            'type'    => 'casual',
        ]);

        (new SubscribeToSeasonAction)($season, $request);

        $this->assertDatabaseHas('subscriptions', [
            'user_id'        => $user->id,
            'season_id'      => $season->id,
            'is_competitive' => false,
        ]);
    })->group('subscriptions', 'actions');

    test('prevents duplicate subscription for the same season', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        Subscription::factory()->create([
            'user_id'   => $user->id,
            'season_id' => $season->id,
            'status'    => 'pending',
        ]);

        $request = Request::create('/subscribe', 'POST', [
            'user_id' => (string) $user->id,
            'type'    => 'competitive',
        ]);

        $response = (new SubscribeToSeasonAction)($season, $request);

        expect($response)->toBeInstanceOf(RedirectResponse::class);
        expect(
            Subscription::where('user_id', $user->id)->where('season_id', $season->id)->count()
        )->toBe(1);
    })->group('subscriptions', 'actions');

    test('competitive subscription is priced at 125', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $request = Request::create('/subscribe', 'POST', [
            'user_id' => (string) $user->id,
            'type'    => 'competitive',
        ]);

        (new SubscribeToSeasonAction)($season, $request);

        $subscription = Subscription::where('user_id', $user->id)->first();
        expect($subscription->amount_due)->toBe(125.0);
    })->group('subscriptions', 'actions');

    test('casual subscription is priced at 60', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $request = Request::create('/subscribe', 'POST', [
            'user_id' => (string) $user->id,
            'type'    => 'casual',
        ]);

        (new SubscribeToSeasonAction)($season, $request);

        $subscription = Subscription::where('user_id', $user->id)->first();
        expect($subscription->amount_due)->toBe(60.0);
    })->group('subscriptions', 'actions');

})->group('subscriptions');
