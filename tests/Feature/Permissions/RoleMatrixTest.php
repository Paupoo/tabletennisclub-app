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
            ->is_committee_member->toBeFalse()
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

describe('the boolean accessors resolve against roles', function (): void {
    it('maps each retired column to its role', function (Role $role, string $attribute): void {
        expect(User::factory()->withRole($role)->create()->{$attribute})->toBeTrue()
            ->and(User::factory()->create()->{$attribute})->toBeFalse();
    })->with([
        'is_admin' => [Role::ADMINISTRATOR, 'is_admin'],
        'is_committee_member' => [Role::COMMITTEE, 'is_committee_member'],
        'is_coach' => [Role::COACH, 'is_coach'],
        'is_selector' => [Role::SELECTIONS, 'is_selector'],
    ]);
});
