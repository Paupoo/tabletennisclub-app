<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\Guardian;
use Tests\Trait\CreateUser;

uses(CreateUser::class);

beforeEach(function (): void {
    $this->admin = $this->createFakeAdmin();
    $this->committeeMember = $this->createFakeCommitteeMember();
    $this->member = $this->createFakeUser();
    $this->guardian = Guardian::factory()->create();
});

describe('viewAny', function (): void {
    it('allows admin', fn () => expect($this->admin->can('viewAny', Guardian::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->committeeMember->can('viewAny', Guardian::class))->toBeTrue());
    it('denies regular member', fn () => expect($this->member->can('viewAny', Guardian::class))->toBeFalse());
});

describe('view', function (): void {
    it('allows admin', fn () => expect($this->admin->can('view', $this->guardian))->toBeTrue());
    it('allows committee member', fn () => expect($this->committeeMember->can('view', $this->guardian))->toBeTrue());
    it('denies regular member', fn () => expect($this->member->can('view', $this->guardian))->toBeFalse());
});

describe('create', function (): void {
    it('allows admin', fn () => expect($this->admin->can('create', Guardian::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->committeeMember->can('create', Guardian::class))->toBeTrue());
    it('denies regular member', fn () => expect($this->member->can('create', Guardian::class))->toBeFalse());
});

describe('update', function (): void {
    it('allows admin', fn () => expect($this->admin->can('update', $this->guardian))->toBeTrue());
    it('allows committee member', fn () => expect($this->committeeMember->can('update', $this->guardian))->toBeTrue());
    it('denies regular member', fn () => expect($this->member->can('update', $this->guardian))->toBeFalse());
});

describe('delete', function (): void {
    it('allows admin', fn () => expect($this->admin->can('delete', $this->guardian))->toBeTrue());
    it('allows committee member', fn () => expect($this->committeeMember->can('delete', $this->guardian))->toBeTrue());
    it('denies regular member', fn () => expect($this->member->can('delete', $this->guardian))->toBeFalse());
});

describe('forceDelete and restore', function (): void {
    it('denies forceDelete for everyone', fn () => expect($this->admin->can('forceDelete', $this->guardian))->toBeFalse());
    it('denies restore for everyone', fn () => expect($this->admin->can('restore', $this->guardian))->toBeFalse());
});
