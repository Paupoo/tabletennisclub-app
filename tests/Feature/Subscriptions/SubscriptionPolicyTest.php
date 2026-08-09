<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->member = User::factory()->isCommitteeMember()->withRole(Role::MEMBERS, Role::SEASONS)->create();
    $this->regular = User::factory()->create();
    $this->subscription = Subscription::factory()->create();
});

// ── create / restore / viewAny (always false) ─────────────────────────────────

describe('create', function (): void {
    it('denies everyone', function (): void {
        expect($this->admin->can('create', Subscription::class))->toBeFalse();
        expect($this->regular->can('create', Subscription::class))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('denies everyone', function (): void {
        expect($this->admin->can('restore', $this->subscription))->toBeFalse();
    });
});

describe('viewAny', function (): void {
    it('denies everyone', function (): void {
        expect($this->admin->can('viewAny', Subscription::class))->toBeFalse();
        expect($this->regular->can('viewAny', Subscription::class))->toBeFalse();
    });
});

// ── delete / forceDelete (admin only) ────────────────────────────────────────

describe('delete', function (): void {
    it('allows admin', fn () => expect($this->admin->can('delete', $this->subscription))->toBeTrue());
    it('denies committee member', fn () => expect($this->member->can('delete', $this->subscription))->toBeFalse());
    it('denies regular user', fn () => expect($this->regular->can('delete', $this->subscription))->toBeFalse());
});

describe('forceDelete', function (): void {
    it('allows admin', fn () => expect($this->admin->can('forceDelete', $this->subscription))->toBeTrue());
    it('denies committee member', fn () => expect($this->member->can('forceDelete', $this->subscription))->toBeFalse());
    it('denies regular user', fn () => expect($this->regular->can('forceDelete', $this->subscription))->toBeFalse());
});

// ── generatePayment (whoever manages affiliations) ───────────────────────────

describe('generatePayment', function (): void {
    it('allows admin', fn () => expect($this->admin->can('generatePayment', $this->subscription))->toBeTrue());
    it('allows the members delegate', fn () => expect($this->member->can('generatePayment', $this->subscription))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('generatePayment', $this->subscription))->toBeFalse());
});

// ── update / view (delegate to season update policy) ──────────────────────────

describe('update and view delegate to season', function (): void {
    it('allows admin to update subscription (via season policy)', fn () => expect($this->admin->can('update', $this->subscription))->toBeTrue());
    it('allows the seasons delegate to update', fn () => expect($this->member->can('update', $this->subscription))->toBeTrue());
    it('denies regular user from updating', fn () => expect($this->regular->can('update', $this->subscription))->toBeFalse());

    it('allows admin to view subscription (via season policy)', fn () => expect($this->admin->can('view', $this->subscription))->toBeTrue());
    it('denies regular user from viewing', fn () => expect($this->regular->can('view', $this->subscription))->toBeFalse());
});
