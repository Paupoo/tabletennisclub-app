<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Observer tests', function (): void {
    it('clears committee_role when user is not a committee member', function (): void {
        $user = User::factory()->create([
            'is_committee_member' => true,
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $user->update([
            'is_committee_member' => false,
        ]);

        expect($user->fresh()->committee_role)->toBeNull();
    });

    it('keeps committee_role when user is a committee member', function (): void {
        $user = User::factory()->create([
            'is_committee_member' => true,
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $user->update([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);

        expect($user->fresh()->committee_role)->toBe(CommitteeRolesEnum::TREASURER);
    });

    it('keeps committee_role when an unrelated field is updated', function (): void {
        $user = User::factory()->create([
            'is_committee_member' => true,
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $user->update(['first_name' => 'Nouveau']);

        expect($user->fresh()->committee_role)->toBe(CommitteeRolesEnum::PRESIDENT);
    });

    it('does not assign a committee_role when user is not a committee member', function (): void {
        $user = User::factory()->create(['is_committee_member' => false]);

        $user->update(['committee_role' => CommitteeRolesEnum::TREASURER]);

        expect($user->fresh()->committee_role)->toBeNull();
    });
})->group('club-admin', 'Users', 'Observers');
