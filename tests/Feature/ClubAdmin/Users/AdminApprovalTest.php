<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\GeneratePaymentReference;
use App\Actions\ClubAdmin\Subscriptions\ApproveTrainingPacksAction;
use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\EnrollInTrainingPackAction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Trainings\Models\TrainingPack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

describe('ApproveTrainingPacksAction', function (): void {

    test('approved packs transition from pending to enrolled', function (): void {
        $subscription = Subscription::factory()->create();
        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 80, 'allow_discount' => true]);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        (new ApproveTrainingPacksAction)($subscription, [$pack1->id, $pack2->id]);

        $pivot1 = $subscription->trainingPacks()->where('training_pack_id', $pack1->id)->first();
        $pivot2 = $subscription->trainingPacks()->where('training_pack_id', $pack2->id)->first();

        expect($pivot1->pivot->status)->toBe('enrolled')
            ->and($pivot2->pivot->status)->toBe('enrolled');
    })->group('admin', 'approval');

    test('non-approved pending packs are detached', function (): void {
        $subscription = Subscription::factory()->create();
        $pack1 = TrainingPack::factory()->create(['price' => 90]);
        $pack2 = TrainingPack::factory()->create(['price' => 80]);

        $subscription->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $subscription->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        (new ApproveTrainingPacksAction)($subscription, [$pack1->id]); // only approve pack1

        expect($subscription->trainingPacks()->where('training_pack_id', $pack1->id)->exists())->toBeTrue()
            ->and($subscription->trainingPacks()->where('training_pack_id', $pack2->id)->exists())->toBeFalse();
    })->group('admin', 'approval');

    test('already enrolled packs are not affected', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['price' => 90]);

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        (new ApproveTrainingPacksAction)($subscription, []);

        $pivot = $subscription->trainingPacks()->where('training_pack_id', $pack->id)->first();
        expect($pivot->pivot->status)->toBe('enrolled');
    })->group('admin', 'approval');

    test('price is recalculated after approval', function (): void {
        $subscription = Subscription::factory()->create(['is_competitive' => false]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => false]);

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        // Pending packs not counted: only recreational base price
        (new CalculatePriceAction)($subscription);
        expect($subscription->fresh()->amount_due)->toBe(60.0);

        (new ApproveTrainingPacksAction)($subscription, [$pack->id]);

        // After approval: base + pack
        expect($subscription->fresh()->amount_due)->toBe(60.0 + 90.0);
    })->group('admin', 'approval', 'pricing');

    test('empty approvedPackIds detaches all pending packs', function (): void {
        $subscription = Subscription::factory()->create();
        $pack = TrainingPack::factory()->create(['price' => 90]);

        $subscription->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        (new ApproveTrainingPacksAction)($subscription, []);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse();
    })->group('admin', 'approval');

});

describe('Admin — refund enrolled pack', function (): void {

    test('removing an enrolled pack detaches it and creates a negative pending payment', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid', 'amount_due' => 215]);
        $pack = TrainingPack::factory()->create(['price' => 90]);
        $subscription->trainingPacks()->attach($pack->id, ['status' => 'enrolled']);

        $packPrice = (float) $pack->price;

        $subscription->trainingPacks()->detach($pack->id);

        (new CalculatePriceAction)($subscription);

        $refundPayment = $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => -$packPrice,
            'amount_paid' => 0,
            'status' => 'pending',
            'payment_method' => 'refund',
        ]);

        expect($subscription->trainingPacks()->where('training_pack_id', $pack->id)->exists())->toBeFalse()
            ->and($refundPayment->amount_due)->toBe(-90.0)
            ->and($refundPayment->status)->toBe('pending')
            ->and($refundPayment->payment_method)->toBe('refund');
    })->group('admin', 'refund');

    test('refund record is identifiable by negative amount_due and payment_method=refund', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid', 'amount_due' => 125]);
        $pack = TrainingPack::factory()->create(['price' => 80]);

        $refundPayment = $subscription->payments()->create([
            'reference' => (new GeneratePaymentReference)(),
            'amount_due' => -80,
            'amount_paid' => 0,
            'status' => 'pending',
            'payment_method' => 'refund',
        ]);

        $refunds = $subscription->payments()
            ->where('payment_method', 'refund')
            ->where('amount_due', '<', 0)
            ->get();

        expect($refunds)->toHaveCount(1)
            ->and($refunds->first()->id)->toBe($refundPayment->id);
    })->group('admin', 'refund');

})->group('admin');

describe('trainingRequestPricingBreakdown — display logic', function (): void {

    beforeEach(function (): void {
        $this->admin = User::factory()->isAdmin()->create();
    });

    test('single new pack, no existing enrolled → no discount, no retro adjustments', function (): void {
        $sub = Subscription::factory()->create(['status' => 'paid', 'is_competitive' => true]);
        $pack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $sub->trainingPacks()->attach($pack->id, ['status' => 'pending']);

        $bd = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $sub->id)
            ->set('approvedPackIds', [$pack->id])
            ->get('trainingRequestPricingBreakdown');

        expect($bd['apply_discount'])->toBeFalse()
            ->and($bd['retro_adjustments'])->toBeEmpty()
            ->and($bd['new_packs'][$pack->id]['discounted'])->toBeFalse()
            ->and($bd['new_packs'][$pack->id]['effective_price'])->toBe(90.0);
    })->group('admin', 'pricing', 'breakdown');

    test('two new discountable packs → both discounted at 80€, no retro adjustments', function (): void {
        $sub = Subscription::factory()->create(['status' => 'paid', 'is_competitive' => true]);
        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $sub->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $sub->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        $bd = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $sub->id)
            ->set('approvedPackIds', [$pack1->id, $pack2->id])
            ->get('trainingRequestPricingBreakdown');

        expect($bd['apply_discount'])->toBeTrue()
            ->and($bd['retro_adjustments'])->toBeEmpty()
            ->and($bd['new_packs'][$pack1->id]['effective_price'])->toBe(80.0)
            ->and($bd['new_packs'][$pack2->id]['effective_price'])->toBe(80.0);
    })->group('admin', 'pricing', 'breakdown');

    test('1 already enrolled + 1 new → retro adjustment on enrolled pack', function (): void {
        $sub = Subscription::factory()->create(['status' => 'paid', 'is_competitive' => true]);
        $existing = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $newPack = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $sub->trainingPacks()->attach($existing->id, ['status' => 'enrolled']);
        $sub->trainingPacks()->attach($newPack->id, ['status' => 'pending']);

        $bd = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $sub->id)
            ->set('approvedPackIds', [$newPack->id])
            ->get('trainingRequestPricingBreakdown');

        expect($bd['apply_discount'])->toBeTrue()
            ->and($bd['retro_adjustments'])->toHaveKey($existing->id)
            ->and($bd['retro_adjustments'][$existing->id]['original_price'])->toBe(90.0)
            ->and($bd['retro_adjustments'][$existing->id]['new_price'])->toBe(80.0)
            ->and($bd['new_packs'][$newPack->id]['effective_price'])->toBe(80.0);
    })->group('admin', 'pricing', 'breakdown');

    test('2 already enrolled + 1 new → no retro adjustment (discount was already active)', function (): void {
        $sub = Subscription::factory()->create(['status' => 'paid', 'is_competitive' => true]);
        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack3 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $sub->trainingPacks()->attach($pack1->id, ['status' => 'enrolled']);
        $sub->trainingPacks()->attach($pack2->id, ['status' => 'enrolled']);
        $sub->trainingPacks()->attach($pack3->id, ['status' => 'pending']);

        $bd = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $sub->id)
            ->set('approvedPackIds', [$pack3->id])
            ->get('trainingRequestPricingBreakdown');

        expect($bd['apply_discount'])->toBeTrue()
            ->and($bd['retro_adjustments'])->toBeEmpty()
            ->and($bd['new_packs'][$pack3->id]['effective_price'])->toBe(80.0);
    })->group('admin', 'pricing', 'breakdown');

    test('fixed-price pack is never discounted regardless of other packs', function (): void {
        $sub = Subscription::factory()->create(['status' => 'paid', 'is_competitive' => true]);
        $discountable = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $fixed = TrainingPack::factory()->create(['price' => 350, 'allow_discount' => false]);
        $sub->trainingPacks()->attach($discountable->id, ['status' => 'pending']);
        $sub->trainingPacks()->attach($fixed->id, ['status' => 'pending']);

        $bd = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $sub->id)
            ->set('approvedPackIds', [$discountable->id, $fixed->id])
            ->get('trainingRequestPricingBreakdown');

        // Only 1 discountable → no discount
        expect($bd['apply_discount'])->toBeFalse()
            ->and($bd['new_packs'][$discountable->id]['discounted'])->toBeFalse()
            ->and($bd['new_packs'][$fixed->id]['discounted'])->toBeFalse()
            ->and($bd['new_packs'][$fixed->id]['effective_price'])->toBe(350.0);
    })->group('admin', 'pricing', 'breakdown');

    test('unchecking a pack dynamically removes discount', function (): void {
        $sub = Subscription::factory()->create(['status' => 'paid', 'is_competitive' => true]);
        $pack1 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $pack2 = TrainingPack::factory()->create(['price' => 90, 'allow_discount' => true]);
        $sub->trainingPacks()->attach($pack1->id, ['status' => 'pending']);
        $sub->trainingPacks()->attach($pack2->id, ['status' => 'pending']);

        $component = Livewire::actingAs($this->admin)
            ->test('pages::club-admin.users.registrations')
            ->set('currentTrainingRequestId', $sub->id)
            ->set('approvedPackIds', [$pack1->id, $pack2->id]);

        expect($component->get('trainingRequestPricingBreakdown')['apply_discount'])->toBeTrue();

        // Uncheck pack2 — now only 1 discountable → no discount
        $component->set('approvedPackIds', [$pack1->id]);

        $bd = $component->get('trainingRequestPricingBreakdown');

        expect($bd['apply_discount'])->toBeFalse()
            ->and($bd['new_packs'])->toHaveKey($pack1->id)
            ->and($bd['new_packs'])->not->toHaveKey($pack2->id);
    })->group('admin', 'pricing', 'breakdown');

})->group('admin');

describe('EnrollInTrainingPackAction — pending flow', function (): void {

    test('subscription with pending pack counts against capacity', function (): void {
        $pack = TrainingPack::factory()->create(['max_participants' => 1, 'price' => 90]);
        $sub1 = Subscription::factory()->create();
        $sub2 = Subscription::factory()->for($sub1->season, 'season')->create();

        (new EnrollInTrainingPackAction)($sub1, $pack); // → pending, fills committed slot
        $status = (new EnrollInTrainingPackAction)($sub2, $pack); // → waiting

        expect($status)->toBe('waiting');
        expect($pack->committedCount())->toBe(1);
        expect($pack->waitlistCount())->toBe(1);
    })->group('admin', 'enrollment');

});
