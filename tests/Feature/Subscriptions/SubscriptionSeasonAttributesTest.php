<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;

describe('Subscription season attributes', function (): void {

    // ==================== DEFAULTS ====================

    test('new subscription has correct defaults for season attributes', function (): void {
        $subscription = Subscription::factory()->create();

        expect($subscription->fresh())
            ->can_drive->toBeFalse()
            ->seats_available->toBeNull()
            ->wants_to_be_captain->toBeFalse()
            ->volunteer_help->toBeFalse();
    })->group('subscriptions', 'season-attributes');

    // ==================== CASTS ====================

    test('season attributes are cast to the correct types', function (): void {
        $subscription = Subscription::factory()->create([
            'can_drive' => 1,
            'seats_available' => '4',
            'wants_to_be_captain' => 1,
            'volunteer_help' => 0,
        ]);

        $fresh = $subscription->fresh();

        expect($fresh->can_drive)->toBeBool()->toBeTrue()
            ->and($fresh->seats_available)->toBeInt()->toBe(4)
            ->and($fresh->wants_to_be_captain)->toBeBool()->toBeTrue()
            ->and($fresh->volunteer_help)->toBeBool()->toBeFalse();
    })->group('subscriptions', 'season-attributes');

    // ==================== FACTORY STATES ====================

    test('driver state sets can_drive and seats_available', function (): void {
        $subscription = Subscription::factory()->driver()->create();

        expect($subscription->fresh())
            ->can_drive->toBeTrue()
            ->seats_available->toBe(4);
    })->group('subscriptions', 'season-attributes');

    test('driver state accepts a custom seat count', function (): void {
        $subscription = Subscription::factory()->driver(7)->create();

        expect($subscription->fresh())
            ->can_drive->toBeTrue()
            ->seats_available->toBe(7);
    })->group('subscriptions', 'season-attributes');

    test('wantsCaptain state sets wants_to_be_captain', function (): void {
        $subscription = Subscription::factory()->wantsCaptain()->create();

        expect($subscription->fresh()->wants_to_be_captain)->toBeTrue();
    })->group('subscriptions', 'season-attributes');

    test('volunteer state sets volunteer_help', function (): void {
        $subscription = Subscription::factory()->volunteer()->create();

        expect($subscription->fresh()->volunteer_help)->toBeTrue();
    })->group('subscriptions', 'season-attributes');

    // ==================== SCOPES ====================

    test('drivers scope returns only subscriptions that can drive', function (): void {
        $driver = Subscription::factory()->driver()->create();
        $nonDriver = Subscription::factory()->create();

        $results = Subscription::drivers()->pluck('id');

        expect($results)->toContain($driver->id)
            ->not->toContain($nonDriver->id);
    })->group('subscriptions', 'season-attributes');

    test('captainVolunteers scope returns only captain candidates', function (): void {
        $captain = Subscription::factory()->wantsCaptain()->create();
        $other = Subscription::factory()->create();

        $results = Subscription::captainVolunteers()->pluck('id');

        expect($results)->toContain($captain->id)
            ->not->toContain($other->id);
    })->group('subscriptions', 'season-attributes');

})->group('subscriptions');
