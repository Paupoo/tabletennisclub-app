<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
});

// ── cancel ────────────────────────────────────────────────────────────────────

describe('cancel', function (): void {
    it('transitions a pending subscription to cancelled', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.cancel', $subscription))
            ->assertRedirect();

        expect($subscription->fresh()->status)->toBe('cancelled');
    });
});

// ── confirm ───────────────────────────────────────────────────────────────────

describe('confirm', function (): void {
    it('transitions a pending subscription to confirmed', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.confirm', $subscription))
            ->assertRedirect();

        expect($subscription->fresh()->status)->toBe('confirmed');
    });
});

// ── unconfirm ─────────────────────────────────────────────────────────────────

describe('unconfirm', function (): void {
    it('transitions a confirmed subscription back to pending', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.unconfirm', $subscription))
            ->assertRedirect();

        expect($subscription->fresh()->status)->toBe('pending');
    });
});

// ── markPaid ──────────────────────────────────────────────────────────────────

describe('markPaid', function (): void {
    it('transitions a confirmed subscription to paid', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'confirmed']);

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.markPaid', $subscription))
            ->assertRedirect();

        expect($subscription->fresh()->status)->toBe('paid');
    });
});

// ── markRefunded ──────────────────────────────────────────────────────────────

describe('markRefunded', function (): void {
    it('transitions a paid subscription to refunded', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'paid']);

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.markRefunded', $subscription))
            ->assertRedirect();

        expect($subscription->fresh()->status)->toBe('refunded');
    });
});

// ── delete ────────────────────────────────────────────────────────────────────

describe('delete', function (): void {
    it('removes the subscription from the database', function (): void {
        $subscription = Subscription::factory()->create();
        $id = $subscription->id;

        $this->actingAs($this->admin)
            ->delete(route('clubAdmin.subscriptions.destroy', $subscription))
            ->assertRedirect();

        expect(Subscription::find($id))->toBeNull();
    });
});

// ── full lifecycle ────────────────────────────────────────────────────────────

describe('full lifecycle', function (): void {
    it('goes through the happy path pending → confirmed → paid', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.confirm', $subscription));
        expect($subscription->fresh()->status)->toBe('confirmed');

        $this->actingAs($this->admin)
            ->post(route('clubAdmin.subscriptions.markPaid', $subscription));
        expect($subscription->fresh()->status)->toBe('paid');
    });
});

// ── guests and unauthorized users ────────────────────────────────────────────

describe('access control', function (): void {
    it('redirects guest to login for cancel', function (): void {
        $subscription = Subscription::factory()->create(['status' => 'pending']);

        $this->post(route('clubAdmin.subscriptions.cancel', $subscription))
            ->assertRedirect(route('login'));
    });
});
