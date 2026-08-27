<?php

declare(strict_types=1);

use App\Actions\User\SyncUserAccessAction;
use App\Data\User\AccessData;
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
| set. It now runs in SyncUserAccessAction, and these scenarios follow it there.
*/

beforeEach(function (): void {
    $this->actor = User::factory()->isAdmin()->create();
});

describe('statutory title follows committee membership', function (): void {
    it('clears committee_role when the user stops being a committee member', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        SyncUserAccessAction::handle($user, new AccessData(isCommitteeMember: false), $this->actor);

        expect($user->fresh()->committee_role)->toBeNull();
    });

    it('keeps committee_role when the user remains a committee member', function (): void {
        $user = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        SyncUserAccessAction::handle(
            $user,
            new AccessData(isCommitteeMember: true, committeeRole: CommitteeRolesEnum::TREASURER),
            $this->actor,
        );

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

        SyncUserAccessAction::handle($user, new AccessData(isCommitteeMember: false), $this->actor);

        expect($user->fresh()->committee_role)->toBeNull();
    });
});

describe('base roles', function (): void {
    it('grants and revokes the base roles from the form booleans', function (): void {
        $user = User::factory()->create();

        SyncUserAccessAction::handle(
            $user,
            new AccessData(isAdmin: true, isCommitteeMember: true, delegations: [Role::COACH->value]),
            $this->actor,
        );

        expect($user->fresh())
            ->hasRole(Role::ADMINISTRATOR->value)->toBeTrue()
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::COACH->value)->toBeTrue();

        SyncUserAccessAction::handle($user, new AccessData(isCommitteeMember: true), $this->actor);

        expect($user->fresh())
            ->hasRole(Role::ADMINISTRATOR->value)->toBeFalse()
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::COACH->value)->toBeFalse();
    });

    it('leaves délégations alone when the caller does not manage them', function (): void {
        $user = User::factory()->withRole(Role::CASH_REGISTER)->create();

        // null, not an empty AccessData — the self-service profile screen edits
        // personal details and knows nothing about duties; an empty one would
        // strip them.
        SyncUserAccessAction::handle($user, null, $this->actor);

        expect($user->fresh()->getRoleNames()->all())->toContain(Role::CASH_REGISTER->value);
    });

    it('refuses to grant a base role through the délégations field', function (): void {
        $user = User::factory()->create();

        SyncUserAccessAction::handle(
            $user,
            new AccessData(delegations: [
                Role::ADMINISTRATOR->value,
                Role::COMMITTEE->value,
                Role::WEBSITE->value,
                'role-inexistant',
            ]),
            $this->actor,
        );

        expect($user->fresh())
            ->hasRole(Role::ADMINISTRATOR->value)->toBeFalse()
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->and($user->fresh()->getRoleNames()->all())->toBe([Role::WEBSITE->value]);
    });

    // The layer travels as one object, so a caller that manages rights states
    // all of them at once: what it leaves out is revoked, and a caller with
    // nothing to say passes null instead. The scenario this replaces asked the
    // old two-level signal to keep duties the call did not mention.
    it('revokes the délégations a rights-managing caller leaves out', function (): void {
        $user = User::factory()->withRole(Role::CASH_REGISTER, Role::WEBSITE)->create();

        SyncUserAccessAction::handle(
            $user,
            new AccessData(isCommitteeMember: true, delegations: [Role::WEBSITE->value]),
            $this->actor,
        );

        expect($user->fresh()->getRoleNames()->all())
            ->not->toContain(Role::CASH_REGISTER->value)
            ->toContain(Role::WEBSITE->value)
            ->toContain(Role::COMMITTEE->value);
    });
});
