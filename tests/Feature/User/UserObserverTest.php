<?php

declare(strict_types=1);

use App\Enums\CommitteeRolesEnum;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('clears committee_role when is_committee_member is set to false', function (): void {
    $user = User::factory()->create([
        'is_committee_member' => true,
        'committee_role' => 'SECRETARY',
    ]);

    $user->update(['is_committee_member' => false]);

    expect($user->fresh()->committee_role)->toBeNull();
});

it('keeps committee_role when user remains a committee member', function (): void {
    $user = User::factory()->create([
        'is_committee_member' => true,
        'committee_role' => 'PRESIDENT',
    ]);

    $user->update(['first_name' => 'Nouveau']);

    expect($user->fresh()->committee_role)->toBe(CommitteeRolesEnum::PRESIDENT);
});

it('does not assign a committee_role when user is not a committee member', function (): void {
    $user = User::factory()->create(['is_committee_member' => false]);

    $user->update(['committee_role' => 'TREASURER']);

    // The saving observer should clear it because is_committee_member is false
    expect($user->fresh()->committee_role)->toBeNull();
});
