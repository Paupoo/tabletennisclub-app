<?php

declare(strict_types=1);

use App\Enums\TournamentStatusEnum;
use App\Models\ClubAdmin\Users\User;
use App\Models\ClubEvents\Tournament\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->member = User::factory()->isCommitteeMember()->create();
    $this->regular = User::factory()->create();
});

// ── viewAny (always true) ──────────────────────────────────────────────────────

describe('viewAny', function (): void {
    it('allows everyone', function (): void {
        expect($this->admin->can('viewAny', Tournament::class))->toBeTrue();
        expect($this->member->can('viewAny', Tournament::class))->toBeTrue();
        expect($this->regular->can('viewAny', Tournament::class))->toBeTrue();
    });
});

// ── view (always false) ────────────────────────────────────────────────────────

describe('view', function (): void {
    it('denies everyone', function (): void {
        $tournament = Tournament::factory()->create();
        expect($this->admin->can('view', $tournament))->toBeFalse();
        expect($this->member->can('view', $tournament))->toBeFalse();
        expect($this->regular->can('view', $tournament))->toBeFalse();
    });
});

// ── create ────────────────────────────────────────────────────────────────────

describe('create', function (): void {
    it('allows admin', fn () => expect($this->admin->can('create', Tournament::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('create', Tournament::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('create', Tournament::class))->toBeFalse());
});

// ── update ────────────────────────────────────────────────────────────────────

describe('update', function (): void {
    it('allows admin', fn () => expect($this->admin->can('update', Tournament::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('update', Tournament::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('update', Tournament::class))->toBeFalse());
});

// ── delete ────────────────────────────────────────────────────────────────────

describe('delete', function (): void {
    it('allows admin', function (): void {
        $t = Tournament::factory()->create();
        expect($this->admin->can('delete', $t))->toBeTrue();
    });
    it('allows committee member', function (): void {
        $t = Tournament::factory()->create();
        expect($this->member->can('delete', $t))->toBeTrue();
    });
    it('denies regular user', function (): void {
        $t = Tournament::factory()->create();
        expect($this->regular->can('delete', $t))->toBeFalse();
    });
});

// ── forceDelete (admin/committee + status ≠ PENDING) ─────────────────────────

describe('forceDelete', function (): void {
    it('allows admin when status is not PENDING', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
        expect($this->admin->can('forceDelete', $t))->toBeTrue();
    });

    it('denies admin when status is PENDING', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        expect($this->admin->can('forceDelete', $t))->toBeFalse();
    });

    it('allows committee member when not PENDING', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
        expect($this->member->can('forceDelete', $t))->toBeTrue();
    });

    it('denies committee member when PENDING', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::PENDING]);
        expect($this->member->can('forceDelete', $t))->toBeFalse();
    });

    it('denies regular user regardless of status', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
        expect($this->regular->can('forceDelete', $t))->toBeFalse();
    });
});

// ── restore (always false) ────────────────────────────────────────────────────

describe('restore', function (): void {
    it('denies everyone', function (): void {
        $t = Tournament::factory()->create();
        expect($this->admin->can('restore', $t))->toBeFalse();
        expect($this->member->can('restore', $t))->toBeFalse();
        expect($this->regular->can('restore', $t))->toBeFalse();
    });
});

// ── updatesBeforeStart (admin/committee + status === PUBLISHED) ───────────────

describe('updatesBeforeStart', function (): void {
    it('allows admin on a PUBLISHED tournament', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
        expect($this->admin->can('updatesBeforeStart', $t))->toBeTrue();
    });

    it('denies admin on a non-PUBLISHED tournament', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
        expect($this->admin->can('updatesBeforeStart', $t))->toBeFalse();
    });

    it('denies regular user even on a PUBLISHED tournament', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
        expect($this->regular->can('updatesBeforeStart', $t))->toBeFalse();
    });
});

// ── updateSubscriptionAsUser (any user, only when PUBLISHED) ──────────────────

describe('updateSubscriptionAsUser', function (): void {
    it('allows any user when tournament is PUBLISHED', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::PUBLISHED]);
        expect($this->regular->can('updateSubscriptionAsUser', $t))->toBeTrue();
    });

    it('denies any user when tournament is not PUBLISHED', function (): void {
        $t = Tournament::factory()->create(['status' => TournamentStatusEnum::DRAFT]);
        expect($this->regular->can('updateSubscriptionAsUser', $t))->toBeFalse();
    });
});
