<?php

declare(strict_types=1);

use App\Actions\User\SyncUserAccessAction;
use App\Data\User\AccessData;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/*
| The rights layer of a member's file — the délégations, the committee seat and
| the statutory title that comes with it — has exactly one writer. Everything
| below is what that writer guarantees whoever calls it: a caller that does not
| manage rights passes null and nothing moves, and a caller that does cannot
| write more than it holds itself.
*/

beforeEach(function (): void {
    $this->admin = User::factory()->isAdmin()->create();
    $this->accessManager = User::factory()->withRole(Role::ACCESS)->create();
});

describe('the explicit "do not touch" signal', function (): void {
    it('leaves the whole layer alone when the caller passes null', function (): void {
        $member = User::factory()->isCommitteeMember()->withRole(Role::CASH_REGISTER)->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);

        SyncUserAccessAction::handle($member, null, $this->admin);

        expect($member->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::CASH_REGISTER->value)->toBeTrue()
            ->committee_role->toBe(CommitteeRolesEnum::TREASURER);
    });

    it('does not mistake "no title" for "do not touch the title"', function (): void {
        $member = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::SECRETARY,
        ]);

        // A committee member whose title is being cleared: null here is a value,
        // not an absence — which is exactly why the layer travels as one object.
        SyncUserAccessAction::handle(
            $member,
            new AccessData(isCommitteeMember: true, committeeRole: null),
            $this->admin,
        );

        expect($member->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->committee_role->toBeNull();
    });
});

describe('what the layer writes', function (): void {
    it('grants and revokes the base roles and the délégations', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(isCommitteeMember: true, delegations: [Role::COACH->value]),
            $this->admin,
        );

        expect($member->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeTrue()
            ->hasRole(Role::COACH->value)->toBeTrue();

        SyncUserAccessAction::handle($member, new AccessData, $this->admin);

        expect($member->fresh()->getRoleNames()->all())->toBe([]);
    });

    it('writes the statutory title, which is a right and no longer a data field', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(isCommitteeMember: true, committeeRole: CommitteeRolesEnum::PRESIDENT),
            $this->admin,
        );

        expect($member->fresh()->committee_role)->toBe(CommitteeRolesEnum::PRESIDENT);
    });

    it('refuses a title to someone who does not sit on the committee', function (): void {
        $member = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::PRESIDENT,
        ]);

        SyncUserAccessAction::handle(
            $member,
            new AccessData(isCommitteeMember: false, committeeRole: CommitteeRolesEnum::PRESIDENT),
            $this->admin,
        );

        expect($member->fresh())
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->committee_role->toBeNull();
    });

    it('refuses a base role slipped into the délégations field', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(delegations: [
                Role::ADMINISTRATOR->value,
                Role::COMMITTEE->value,
                Role::WEBSITE->value,
                'role-inexistant',
            ]),
            $this->admin,
        );

        expect($member->fresh()->getRoleNames()->all())->toBe([Role::WEBSITE->value]);
    });
});

describe('anti-escalation, enforced by the writer itself', function (): void {
    it('refuses the reserved délégation to anyone but an administrator', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(delegations: [Role::ACCESS->value, Role::BAR->value]),
            $this->accessManager,
        );

        expect($member->fresh()->getRoleNames()->all())->toBe([Role::BAR->value]);
    });

    it('lets an administrator hand the reserved délégation over', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(delegations: [Role::ACCESS->value]),
            $this->admin,
        );

        expect($member->fresh()->hasRole(Role::ACCESS->value))->toBeTrue();
    });

    it('neither grants nor revokes the reserved délégation on a non-administrator save', function (): void {
        // Rendered locked, so their form cannot move it in either direction.
        $colleague = User::factory()->withRole(Role::ACCESS, Role::BAR)->create();

        SyncUserAccessAction::handle(
            $colleague,
            new AccessData(delegations: [Role::BAR->value]),
            $this->accessManager,
        );

        expect($colleague->fresh()->getRoleNames()->all())
            ->toContain(Role::ACCESS->value)
            ->toContain(Role::BAR->value);
    });

    it('keeps the administrator checkbox out of reach of an access manager', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(isAdmin: true, isCommitteeMember: true),
            $this->accessManager,
        );

        expect($member->fresh())
            ->hasRole(Role::ADMINISTRATOR->value)->toBeFalse()
            ->hasRole(Role::COMMITTEE->value)->toBeTrue();
    });

    it('does not let an access manager demote an administrator either', function (): void {
        $otherAdmin = User::factory()->isAdmin()->create();

        SyncUserAccessAction::handle(
            $otherAdmin,
            new AccessData(isAdmin: false),
            $this->accessManager,
        );

        expect($otherAdmin->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeTrue();
    });

    it('writes nothing at all when the caller may not manage rights', function (): void {
        $membersDelegate = User::factory()->withRole(Role::MEMBERS)->create();
        $member = User::factory()->withRole(Role::BAR)->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(isAdmin: true, isCommitteeMember: true, delegations: [Role::TREASURY->value]),
            $membersDelegate,
        );

        expect($member->fresh()->getRoleNames()->all())->toBe([Role::BAR->value]);
    });

    it('writes nothing on your own file, administrators included', function (): void {
        SyncUserAccessAction::handle(
            $this->admin,
            new AccessData(isAdmin: false, delegations: []),
            $this->admin,
        );

        expect($this->admin->fresh()->hasRole(Role::ADMINISTRATOR->value))->toBeTrue();
    });
});

describe('the audit trail', function (): void {
    /*
     | The shape matters as much as the fact. The audit screen renders its Details
     | column from `attributes` against `old`; an entry logged under any other key
     | shows up as a row with an empty diff, which is how this one first shipped.
     */
    it('records roles_changed in the shape the audit screen reads', function (): void {
        $member = User::factory()->withRole(Role::BAR)->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(delegations: [Role::WEBSITE->value]),
            $this->admin,
        );

        $activity = Activity::query()->where('event', 'roles_changed')->latest('id')->first();

        expect($activity)->not->toBeNull()
            ->and($activity->subject_id)->toBe($member->id)
            ->and($activity->causer_id)->toBe($this->admin->id)
            ->and($activity->attribute_changes['old']['roles'])->toBe(Role::BAR->value)
            ->and($activity->attribute_changes['attributes']['roles'])->toBe(Role::WEBSITE->value);
    });

    it('leaves the screen a diff it can actually render', function (): void {
        $member = User::factory()->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(isCommitteeMember: true, delegations: [Role::BAR->value]),
            $this->admin,
        );

        $changes = Activity::query()->where('event', 'roles_changed')->latest('id')->first()->attribute_changes;

        expect($changes)->toHaveKey('attributes')
            ->and($changes['attributes']['roles'])->toBe(Role::BAR->value . ', ' . Role::COMMITTEE->value)
            ->and($changes['old']['roles'])->toBe('');
    });

    it('stays silent when the set of roles does not move', function (): void {
        $member = User::factory()->withRole(Role::BAR)->create();

        SyncUserAccessAction::handle(
            $member,
            new AccessData(delegations: [Role::BAR->value]),
            $this->admin,
        );

        expect(Activity::query()->where('event', 'roles_changed')->count())->toBe(0);
    });
});
