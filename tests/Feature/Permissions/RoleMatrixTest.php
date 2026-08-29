<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| The matrix in the Role enum is the single source of truth for who may do what.
| These are its invariants — the properties that must hold whatever is added to
| it later, so that extending the matrix cannot quietly break the model.
*/

describe('matrix invariants', function (): void {
    it('gives the administrator every permission', function (): void {
        expect(Role::ADMINISTRATOR->permissions())
            ->toHaveCount(count(Permission::cases()));
    });

    it('never lets the committee baseline grant a management permission', function (): void {
        $managing = array_filter(
            Role::COMMITTEE->permissions(),
            static fn (Permission $p): bool => ! str_ends_with($p->value, '.view'),
        );

        expect($managing)->toBe([]);
    });

    it('exposes every délégation as assignable to anyone', function (): void {
        expect(Role::delegations())
            ->not->toContain(Role::ADMINISTRATOR)
            ->not->toContain(Role::COMMITTEE)
            ->toHaveCount(count(Role::cases()) - 2);
    });

    it('labels and describes every role', function (Role $role): void {
        expect($role->label())->not->toBe('')
            ->and($role->description())->not->toBe('');
    })->with(Role::cases());

    it('only ever suggests délégations for a statutory title', function (CommitteeRolesEnum $title): void {
        foreach (Role::suggestedFor($title) as $suggested) {
            expect($suggested->isDelegation())->toBeTrue();
        }
    })->with(CommitteeRolesEnum::cases());
});

describe('what a délégation actually grants', function (): void {
    it('grants the treasury delegate the treasury, and nothing else', function (): void {
        $user = User::factory()->withRole(Role::TREASURY)->create();

        expect($user)
            ->can(Permission::PaymentsReconcile->value)->toBeTrue()
            ->can(Permission::TransactionsImport->value)->toBeTrue()
            // the cash box is a separate duty — that is the whole point of the split
            ->can(Permission::CashRegisterHolderChange->value)->toBeFalse()
            ->can(Permission::UsersCreate->value)->toBeFalse();
    });

    it('lets a plain member hold the cash register without joining the committee', function (): void {
        $xavier = User::factory()->withRole(Role::CASH_REGISTER)->create();

        expect($xavier)
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->committee_role->toBeNull()
            ->can(Permission::CashRegisterHolderChange->value)->toBeTrue()
            ->can(Permission::PaymentsReconcile->value)->toBeFalse();
    });

    it('stacks délégations', function (): void {
        $user = User::factory()->withRole(Role::WEBSITE, Role::MEETINGS)->create();

        expect($user)
            ->can(Permission::NewsPostsManage->value)->toBeTrue()
            ->can(Permission::MeetingsMinutesManage->value)->toBeTrue()
            ->can(Permission::BarProductsManage->value)->toBeFalse();
    });

    it('grants the administrator everything without a Gate::before bypass', function (Permission $permission): void {
        expect(User::factory()->isAdmin()->create()->can($permission->value))->toBeTrue();
    })->with(Permission::cases());
});

describe('policies still outrank permissions', function (): void {
    it('stops an administrator deleting their own account', function (): void {
        $admin = User::factory()->isAdmin()->create();

        expect($admin->can('delete', $admin))->toBeFalse()
            ->and($admin->can('delete', User::factory()->create()))->toBeTrue();
    });
});

/*
| This block used to check the compatibility accessors that stood in for the four
| boolean columns. The columns and the accessors are gone; what it was really
| protecting — that each retired flag has a role carrying the same meaning — is
| asserted here directly.
*/
describe('the retired flags each have a role', function (): void {
    it('grants the role, and nothing to a member without it', function (Role $role): void {
        expect(User::factory()->withRole($role)->create()->hasRole($role->value))->toBeTrue()
            ->and(User::factory()->create()->hasRole($role->value))->toBeFalse();
    })->with([
        'is_admin' => Role::ADMINISTRATOR,
        'is_committee_member' => Role::COMMITTEE,
        'is_coach' => Role::COACH,
        'is_selector' => Role::SELECTIONS,
    ]);

    it('no longer exposes the columns as attributes', function (string $flag): void {
        expect(User::factory()->create()->getAttributes())->not->toHaveKey($flag);
    })->with(['is_admin', 'is_committee_member', 'is_coach', 'is_selector']);
});

/*
| `acces` is the délégation that hands out délégations. It is the only one whose
| own checkbox an administrator has to tick, and the only one that reaches a
| member's file without being allowed to edit it — hence its own block.
*/
describe('the access delegation', function (): void {
    it('reaches the member file without being allowed to edit it', function (): void {
        expect(Role::ACCESS->permissions())
            ->toContain(Permission::UsersView)
            ->toContain(Permission::AccessManage)
            ->not->toContain(Permission::UsersUpdate);
    });

    it('stays a délégation even though only an administrator may hand it out', function (): void {
        expect(Role::ACCESS->isDelegation())->toBeTrue()
            ->and(Role::ACCESS->isReservedToAdministrators())->toBeTrue();
    });

    it('is the only role the matrix reserves to administrators', function (): void {
        $reserved = array_values(array_filter(
            Role::cases(),
            static fn (Role $role): bool => $role->isReservedToAdministrators(),
        ));

        expect($reserved)->toBe([Role::ACCESS]);
    });

    it('is never suggested by a statutory title', function (CommitteeRolesEnum $title): void {
        expect(Role::suggestedFor($title))->not->toContain(Role::ACCESS);
    })->with(CommitteeRolesEnum::cases());

    it('grants its holder the rights layer and nothing of the data layer', function (): void {
        $delegate = User::factory()->withRole(Role::ACCESS)->create();

        expect($delegate)
            ->can(Permission::UsersView->value)->toBeTrue()
            ->can(Permission::AccessManage->value)->toBeTrue()
            ->can(Permission::UsersUpdate->value)->toBeFalse()
            ->can(Permission::UsersCreate->value)->toBeFalse();
    });
});
