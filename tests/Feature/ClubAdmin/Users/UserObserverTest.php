<?php

declare(strict_types=1);

namespace Tests\Feature\ClubAdmin\Users;

use App\Enums\CommitteeRolesEnum;
use App\Models\ClubAdmin\Users\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('User Observer tests', function () {
    it('clears committee_role when user is not a committee member', function () {
        $user = User::factory()->create([
            'is_committee_member' => true,
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);
    
        $user->update([
            'is_committee_member' => false,
        ]);
    
        expect($user->fresh()->committee_role)->toBeNull();
    });
    
    it('keeps committee_role when user is a committee member', function () {
        $user = User::factory()->create([
            'is_committee_member' => true,
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);
    
        $user->update([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);
    
        expect($user->fresh()->committee_role)->toBe(CommitteeRolesEnum::TREASURER);
    });
})->group('Users', 'Observers');