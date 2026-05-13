<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Subscriptions\CalculatePriceAction;
use App\Actions\ClubAdmin\Subscriptions\CreateSubscriptionAction;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Interclub\Season;
use App\Models\ClubEvents\Training\TrainingPack;

describe('Subscription Business Rules', function () {

    // ==================== SAISON ACTIVE ====================

    test('user can only subscribe to active season', function () {
        $user = User::factory()->create();
        $inactiveSeason = Season::factory()->create(['is_active' => false, 'registrations_open' => false]);

        $action = new CreateSubscriptionAction;

        expect(fn () => $action->execute($user, $inactiveSeason))
            ->toThrow(\DomainException::class, 'Cannot subscribe to an inactive season');
    })->group('subscriptions', 'business-rules');

    test('user can subscribe to active season with open registrations', function () {
        $user = User::factory()->create();
        $activeSeason = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $action = new CreateSubscriptionAction;
        $subscription = $action->execute($user, $activeSeason);

        expect($subscription->exists)->toBeTrue()
            ->and($subscription->status)->toBe('pending')
            ->and($subscription->season_id)->toBe($activeSeason->id);
    })->group('subscriptions', 'business-rules');

    // ==================== INSCRIPTIONS OUVERTES/FERMEES ====================

    test('user cannot subscribe when registrations are closed', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => false]);

        $action = new CreateSubscriptionAction;

        expect(fn () => $action->execute($user, $season))
            ->toThrow(\DomainException::class, 'Registrations are currently closed');
    })->group('subscriptions', 'business-rules');

    test('user can subscribe after registrations are opened', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => false]);

        $season->openRegistrations();

        $action = new CreateSubscriptionAction;
        $subscription = $action->execute($user, $season);

        expect($subscription->status)->toBe('pending');
    })->group('subscriptions', 'business-rules');

    test('user cannot subscribe after registrations are closed again', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $season->closeRegistrations();

        $action = new CreateSubscriptionAction;

        expect(fn () => $action->execute($user, $season->fresh()))
            ->toThrow(\DomainException::class, 'Registrations are currently closed');
    })->group('subscriptions', 'business-rules');

    // ==================== UNE SEULE SUBSCRIPTION ====================

    test('user cannot have multiple subscriptions for same season', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $action = new CreateSubscriptionAction;

        // Première subscription OK
        $action->execute($user, $season);

        // Deuxième subscription KO
        expect(fn () => $action->execute($user, $season))
            ->toThrow(\DomainException::class, 'already has a subscription');
    })->group('subscriptions', 'business-rules');

    test('user can resubscribe after cancellation', function () {
        $user = User::factory()->create();
        $season = Season::factory()->create(['is_active' => true, 'registrations_open' => true]);

        $action = new CreateSubscriptionAction;

        // Première subscription
        $first = $action->execute($user, $season);
        $first->update(['status' => 'cancelled']);

        // Deuxième subscription OK (première annulée)
        $second = $action->execute($user, $season);

        expect($second->id)->not->toBe($first->id)
            ->and($second->status)->toBe('pending');
    })->group('subscriptions', 'business-rules');

    // ==================== CALCUL PRIX ====================

    test('calculates price for competitive member without trainings', function () {
        $subscription = Subscription::factory()->create([
            'is_competitive' => true,
            'has_other_family_members' => false,
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(125.0);
    })->group('subscriptions', 'pricing');

    test('calculates price for recreative member without trainings', function () {
        $subscription = Subscription::factory()->create([
            'is_competitive' => false,
            'has_other_family_members' => false,
        ]);

        (new CalculatePriceAction)($subscription);

        expect($subscription->fresh()->amount_due)->toBe(60.0);
    })->group('subscriptions', 'pricing');

    test('applies family discount')->todo('Implement family discount logic')->group('subscriptions', 'pricing');

    test('calculates price with one training pack', function () {
        $subscription = Subscription::factory()->create([
            'is_competitive' => false,
            'has_other_family_members' => false,
        ]);

        $pack = TrainingPack::factory()->create();
        $subscription->trainingPacks()->attach($pack->id);

        (new CalculatePriceAction)($subscription);

        // 60 + (1 * 90) = 150
        expect($subscription->fresh()->amount_due)->toBe(150.0)
            ->and($subscription->fresh()->trainings_count)->toBe(1);
    })->group('subscriptions', 'pricing');

    test('first training costs 90 for a solo member', function () {
        $subscription = Subscription::factory()->create([
            'is_competitive' => false,
            'has_other_family_members' => false,
        ]);

        $subscription->trainingPacks()->attach(TrainingPack::factory()->create()->id);

        (new CalculatePriceAction)($subscription);

        // unit price should be 90 for a single session
        expect($subscription->fresh()->training_unit_price)->toBe(90.0);
    })->group('subscriptions', 'pricing');

    test('applies discount for multiple training packs', function () {
        $subscription = Subscription::factory()->create([
            'is_competitive' => false,
            'has_other_family_members' => false,
        ]);

        $packs = TrainingPack::factory()->count(2)->create();
        $subscription->trainingPacks()->attach($packs->pluck('id'));

        (new CalculatePriceAction)($subscription);

        // 60 + (2 * 80) = 220
        expect($subscription->fresh()->amount_due)->toBe(220.0)
            ->and($subscription->fresh()->training_unit_price)->toBe(80.0);
    })->group('subscriptions', 'pricing');

    test('discounted training unit price applies from 2 sessions onwards', function () {
        $subscription = Subscription::factory()->create([
            'is_competitive' => true,
            'has_other_family_members' => false,
        ]);

        $subscription->trainingPacks()->attach(TrainingPack::factory()->count(3)->create()->pluck('id'));

        (new CalculatePriceAction)($subscription);

        // 125 + (3 * 80) = 365
        expect($subscription->fresh()->amount_due)->toBe(365.0)
            ->and($subscription->fresh()->training_unit_price)->toBe(80.0)
            ->and($subscription->fresh()->trainings_count)->toBe(3);
    })->group('subscriptions', 'pricing');

    test('complex pricing: competitive + family + 3 trainings')->todo()->group('subscriptions', 'pricing');

    // ==================== WORKFLOW ====================

    test('subscription workflow: pending → confirmed → paid', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        expect($subscription->status)->toBe('pending');

        $subscription->confirm();
        expect($subscription->fresh()->status)->toBe('confirmed');

        $subscription->markAsPaid();
        expect($subscription->fresh()->status)->toBe('paid');
    })->group('subscriptions', 'workflow');

    test('pending subscription can be cancelled', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $subscription->cancel();

        expect($subscription->fresh()->status)->toBe('cancelled');
    })->group('subscriptions', 'workflow');

    test('confirmed subscription can be cancelled', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $subscription->cancel();

        expect($subscription->fresh()->status)->toBe('cancelled');
    })->group('subscriptions', 'workflow');

    test('confirmed subscription can be unconfirmed back to pending', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $subscription->unconfirm();

        expect($subscription->fresh()->status)->toBe('pending');
    })->group('subscriptions', 'workflow');

    test('cannot mark pending subscription as paid directly', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        expect(fn () => $subscription->markAsPaid())
            ->toThrow(\LogicException::class, 'Cannot mark as paid from pending status');
    })->group('subscriptions', 'workflow');

    test('cannot confirm an already confirmed subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        expect(fn () => $subscription->confirm())
            ->toThrow(\LogicException::class, 'already confirmed');
    })->group('subscriptions', 'workflow');

    test('cannot cancel a paid subscription', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        expect(fn () => $subscription->cancel())
            ->toThrow(\LogicException::class, 'Cannot cancel a paid subscription');
    })->group('subscriptions', 'workflow');

    test('cannot modify training packs after confirmation', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        expect(fn () => $subscription->trainingPacks()->sync([1, 2]))
            ->toThrow(\DomainException::class, 'cannot be modified');
    })->group('subscriptions', 'workflow')->skip('Needs SyncTrainingPackAction integration');

    // ==================== PAYMENT GENERATION ====================

    test('confirmed subscription can generate payment', function () {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        expect($subscription->canGeneratePayment())->toBeTrue();
    })->group('subscriptions', 'payments');

    test('pending subscription cannot generate payment', function () {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        expect($subscription->canGeneratePayment())->toBeFalse();
    })->group('subscriptions', 'payments');

    test('paid subscription cannot generate another payment', function () {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        expect($subscription->canGeneratePayment())->toBeFalse();
    })->group('subscriptions', 'payments');

})->group('subscriptions');
