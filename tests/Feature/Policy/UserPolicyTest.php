<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->member = User::factory()->isCommitteeMember()->create();
    $this->regular = User::factory()->create();
});

// ── index / view (always true) ────────────────────────────────────────────────

describe('index', function (): void {
    it('allows everyone', function (): void {
        expect($this->regular->can('index', User::class))->toBeTrue();
    });
});

describe('view', function (): void {
    it('allows everyone to view any user profile', function (): void {
        $target = User::factory()->create();
        expect($this->regular->can('view', $target))->toBeTrue();
        expect($this->admin->can('view', $target))->toBeTrue();
    });
});

// ── create / update / delete / sendEmail / force-list ────────────────────────

describe('create', function (): void {
    it('allows admin', fn () => expect($this->admin->can('create', User::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('create', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('create', User::class))->toBeFalse());
});

describe('update', function (): void {
    it('allows admin', fn () => expect($this->admin->can('update', User::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('update', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('update', User::class))->toBeFalse());
});

describe('delete', function (): void {
    it('allows admin to delete another user', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('delete', $target))->toBeTrue();
    });
    it('allows committee member to delete', function (): void {
        $target = User::factory()->create();
        expect($this->member->can('delete', $target))->toBeTrue();
    });
    it('denies regular user', function (): void {
        $target = User::factory()->create();
        expect($this->regular->can('delete', $target))->toBeFalse();
    });
});

describe('sendEmail', function (): void {
    it('allows admin', fn () => expect($this->admin->can('sendEmail', User::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('sendEmail', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('sendEmail', User::class))->toBeFalse());
});

describe('setOrUpdateForceList', function (): void {
    it('allows admin', fn () => expect($this->admin->can('setOrUpdateForceList', User::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('setOrUpdateForceList', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('setOrUpdateForceList', User::class))->toBeFalse());
});

describe('deleteForceList', function (): void {
    it('allows admin', fn () => expect($this->admin->can('deleteForceList', User::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('deleteForceList', User::class))->toBeFalse());
});

// ── forceDelete / restore (always false) ─────────────────────────────────────

describe('forceDelete', function (): void {
    it('denies everyone including admin', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('forceDelete', $target))->toBeFalse();
    });
});

describe('restore', function (): void {
    it('denies everyone including admin', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('restore', $target))->toBeFalse();
    });
});

// ── selfDelete (own account only) ────────────────────────────────────────────

describe('selfDelete', function (): void {
    it('allows user to delete their own account', function (): void {
        expect($this->regular->can('selfDelete', $this->regular))->toBeTrue();
    });

    it('denies deleting another user account', function (): void {
        $other = User::factory()->create();
        expect($this->regular->can('selfDelete', $other))->toBeFalse();
    });
});

// ── manageSubscription (admin | committee | self) ─────────────────────────────

describe('manageSubscription', function (): void {
    it('allows admin to manage any subscription', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('manageSubscription', $target))->toBeTrue();
    });

    it('allows user to manage their own subscription', function (): void {
        expect($this->regular->can('manageSubscription', $this->regular))->toBeTrue();
    });

    it('denies user to manage someone else subscription', function (): void {
        $other = User::factory()->create();
        expect($this->regular->can('manageSubscription', $other))->toBeFalse();
    });
});

// ── updatePassword (admin | committee | self) ─────────────────────────────────

describe('updatePassword', function (): void {
    it('allows admin to update any password', function (): void {
        $target = User::factory()->create();
        expect($this->admin->can('updatePassword', $target))->toBeTrue();
    });

    it('allows user to update their own password', function (): void {
        expect($this->regular->can('updatePassword', $this->regular))->toBeTrue();
    });

    it('denies user from updating someone else password', function (): void {
        $other = User::factory()->create();
        expect($this->regular->can('updatePassword', $other))->toBeFalse();
    });
});
