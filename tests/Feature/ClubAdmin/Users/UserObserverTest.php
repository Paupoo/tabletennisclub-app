<?php

declare(strict_types=1);

use App\Actions\User\SyncUserRolesAction;
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
| set. It now runs in SyncUserRolesAction, and these scenarios follow it there.
*/

describe('statutory title follows committee membership', function (): void {
    it('clears committee_role when the user stops being a committee member', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: false);

        expect($user->fresh()->committee_role)->toBeNull();
    });

    it('keeps committee_role when the user remains a committee member', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        $user->update(['committee_role' => CommitteeRolesEnum::TREASURER]);
        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: true);

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

        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: false);

        expect($user->fresh()->committee_role)->toBeNull();
    });
});

describe('base roles', function (): void {
    it('grants and revokes the base roles from the form booleans', function (): void {
        $user = User::factory()->create();

        SyncUserRolesAction::handle($user, isAdmin: true, isCommitteeMember: true, delegations: [Role::COACH->value]);

        expect($user->fresh())
            ->is_admin->toBeTrue()
            ->is_committee_member->toBeTrue()
            ->is_coach->toBeTrue();

        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: true, delegations: []);

        expect($user->fresh())
            ->is_admin->toBeFalse()
            ->is_committee_member->toBeTrue()
            ->is_coach->toBeFalse();
    });

    it('leaves délégations alone when the caller does not manage them', function (): void {
        $user = User::factory()->withRole(Role::CASH_REGISTER)->create();

        // null, not [] — the self-service profile screen edits personal details
        // and knows nothing about duties; passing [] there would strip them.
        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: false, delegations: null);

        expect($user->fresh()->getRoleNames()->all())->toContain(Role::CASH_REGISTER->value);
    });

    it('refuses to grant a base role through the délégations field', function (): void {
        $user = User::factory()->create();

        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: false, delegations: [
            Role::ADMINISTRATOR->value,
            Role::COMMITTEE->value,
            Role::WEBSITE->value,
            'role-inexistant',
        ]);

        expect($user->fresh())
            ->is_admin->toBeFalse()
            ->is_committee_member->toBeFalse()
            ->and($user->fresh()->getRoleNames()->all())->toBe([Role::WEBSITE->value]);
    });

    it('never revokes a délégation the member holds elsewhere', function (): void {
        $user = User::factory()->withRole(Role::CASH_REGISTER, Role::WEBSITE)->create();

        SyncUserRolesAction::handle($user, isAdmin: false, isCommitteeMember: true);

        expect($user->fresh()->getRoleNames()->all())
            ->toContain(Role::CASH_REGISTER->value)
            ->toContain(Role::WEBSITE->value)
            ->toContain(Role::COMMITTEE->value);
    });
});
