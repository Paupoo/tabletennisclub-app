<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Contact\Models\Contact;
use App\Domains\ClubAdmin\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ContactPolicy: all actions require is_admin || is_committee_member.

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->member = User::factory()->isCommitteeMember()->create();
    $this->regular = User::factory()->create();
    $this->contact = Contact::factory()->create();
});

describe('viewAny', function (): void {
    it('allows admin', fn () => expect($this->admin->can('viewAny', Contact::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('viewAny', Contact::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('viewAny', Contact::class))->toBeFalse());
});

describe('view', function (): void {
    it('allows admin', fn () => expect($this->admin->can('view', $this->contact))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('view', $this->contact))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('view', $this->contact))->toBeFalse());
});

describe('create', function (): void {
    it('allows admin', fn () => expect($this->admin->can('create', Contact::class))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('create', Contact::class))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('create', Contact::class))->toBeFalse());
});

describe('update', function (): void {
    it('allows admin', fn () => expect($this->admin->can('update', $this->contact))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('update', $this->contact))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('update', $this->contact))->toBeFalse());
});

describe('delete', function (): void {
    it('allows admin', fn () => expect($this->admin->can('delete', $this->contact))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('delete', $this->contact))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('delete', $this->contact))->toBeFalse());
});

describe('sendEmail', function (): void {
    it('allows admin', fn () => expect($this->admin->can('sendEmail', $this->contact))->toBeTrue());
    it('allows committee member', fn () => expect($this->member->can('sendEmail', $this->contact))->toBeTrue());
    it('denies regular user', fn () => expect($this->regular->can('sendEmail', $this->contact))->toBeFalse());
});
