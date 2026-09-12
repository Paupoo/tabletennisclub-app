<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Subscriptions\Attestations\Models\MutualAttestation;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;

/*
| Handing out the club seal is not the same duty as editing a member's address,
| so the attestations délégation stands on its own. The members delegate may do
| neither, and that separation is the reason the role exists.
*/

beforeEach(function (): void {
    $this->office = User::factory()->withRole(Role::ATTESTATIONS)->create();
    $this->membersDelegate = User::factory()->withRole(Role::MEMBERS)->create();
    $this->administrator = User::factory()->isAdmin()->create();
    $this->member = User::factory()->create();
});

it('lets the attestations délégation see, issue and withdraw', function (): void {
    $attestation = MutualAttestation::factory()->create();

    expect($this->office->can('viewAny', MutualAttestation::class))->toBeTrue()
        ->and($this->office->can('create', MutualAttestation::class))->toBeTrue()
        ->and($this->office->can('revoke', $attestation))->toBeTrue();
});

it('keeps the members delegate out of it', function (): void {
    $attestation = MutualAttestation::factory()->create();

    expect($this->membersDelegate->can('viewAny', MutualAttestation::class))->toBeFalse()
        ->and($this->membersDelegate->can('create', MutualAttestation::class))->toBeFalse()
        ->and($this->membersDelegate->can('revoke', $attestation))->toBeFalse();
});

it('gives the délégation the club record, because the forms print it', function (): void {
    expect($this->office->can(Permission::ClubUpdate->value))->toBeTrue()
        ->and($this->office->can(Permission::AttestationsConfigure->value))->toBeTrue()
        ->and($this->membersDelegate->can(Permission::AttestationsConfigure->value))->toBeFalse();
});

it('lets the member fetch their own certificate back, and nobody else theirs', function (): void {
    $mine = MutualAttestation::factory()->create(['user_id' => $this->member->id]);
    $someone = MutualAttestation::factory()->create();

    expect($this->member->can('download', $mine))->toBeTrue()
        ->and($this->member->can('download', $someone))->toBeFalse()
        ->and($this->office->can('download', $someone))->toBeTrue();
});

it('refuses to withdraw one that is already withdrawn', function (): void {
    $attestation = MutualAttestation::factory()->revoked()->create();

    expect($this->office->can('revoke', $attestation))->toBeFalse()
        ->and($this->administrator->can('revoke', $attestation))->toBeFalse();
});

it('never lets anyone edit or delete what the club has certified', function (): void {
    $attestation = MutualAttestation::factory()->create();

    expect($this->administrator->can('update', $attestation))->toBeFalse()
        ->and($this->administrator->can('delete', $attestation))->toBeFalse();
});
