<?php

declare(strict_types=1);

use App\Actions\User\SyncBaseRolesAction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The "a non committee member holds no statutory title" rule used to live in
| UserObserver::saving(). It could not stay there once `is_committee_member`
| started resolving against roles: roles only exist after the row does, so on
| creation the observer read "not a committee member" and wiped the title being
| set. It now runs in SyncBaseRolesAction, and these scenarios follow it there.
*/

describe('statutory title follows committee membership', function (): void {
    it('clears committee_role when the user stops being a committee member', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        SyncBaseRolesAction::handle($user, isAdmin: false, isCommitteeMember: false, isCoach: false);

        expect($user->fresh()->committee_role)->toBeNull();
    });

    it('keeps committee_role when the user remains a committee member', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $user->update(['committee_role' => CommitteeRolesEnum::TREASURER]);
        SyncBaseRolesAction::handle($user, isAdmin: false, isCommitteeMember: true, isCoach: false);

        expect($user->fresh()->committee_role)->toBe(CommitteeRolesEnum::TREASURER);
    });

    it('keeps committee_role when an unrelated field is updated', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $user->update(['first_name' => 'Nouveau']);

        expect($user->fresh()->committee_role)->toBe(CommitteeRolesEnum::PRESIDENT);
    });

    it('does not let a plain member keep a committee_role', function (): void {
        $user = User::factory()->create(['committee_role' => CommitteeRolesEnum::TREASURER]);

        SyncBaseRolesAction::handle($user, isAdmin: false, isCommitteeMember: false, isCoach: false);

        expect($user->fresh()->committee_role)->toBeNull();
    });
});

describe('base roles', function (): void {
    it('grants and revokes the base roles from the form booleans', function (): void {
        $user = User::factory()->create();

        SyncBaseRolesAction::handle($user, isAdmin: true, isCommitteeMember: true, isCoach: true);

        expect($user->fresh())
            ->is_admin->toBeTrue()
            ->is_committee_member->toBeTrue()
            ->is_coach->toBeTrue();

        SyncBaseRolesAction::handle($user, isAdmin: false, isCommitteeMember: true, isCoach: false);

        expect($user->fresh())
            ->is_admin->toBeFalse()
            ->is_committee_member->toBeTrue()
            ->is_coach->toBeFalse();
    });

    it('never revokes a délégation the member holds elsewhere', function (): void {
        $user = User::factory()->withRole(Role::CASH_REGISTER, Role::WEBSITE)->create();

        SyncBaseRolesAction::handle($user, isAdmin: false, isCommitteeMember: true, isCoach: false);

        expect($user->fresh()->getRoleNames()->all())
            ->toContain(Role::CASH_REGISTER->value)
            ->toContain(Role::WEBSITE->value)
            ->toContain(Role::COMMITTEE->value);
    });
});
