<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\CommitteeRolesEnum;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
| Treasury used to answer to committee membership, plus three divergent
| definitions of "treasurer" — the dashboard granted one set, User::canManageFinances()
| another, the cash register a third. Each screen now answers to the délégation
| that owns it, so holding the cash box and reconciling the accounts are finally
| separable — which is the whole reason the club wanted this.
*/

beforeEach(function (): void {
    $this->plainCommittee = User::factory()->isCommitteeMember()->create();
});

describe('the screens answer to their own delegation', function (): void {
    it('lets each delegate in, and keeps the others out', function (string $routeName, Role $granting, Role $other): void {
        $delegate = User::factory()->withRole($granting)->create();
        $stranger = User::factory()->withRole($other)->create();

        $this->actingAs($delegate)->get(route($routeName))->assertOk();
        $this->actingAs($stranger)->get(route($routeName))->assertForbidden();
    })->with([
        'payments' => ['admin.treasury.payments', Role::TREASURY, Role::CASH_REGISTER],
        'transactions' => ['admin.treasury.transactions', Role::TREASURY, Role::FINES],
        'fines' => ['admin.treasury.fines', Role::FINES, Role::TREASURY],
        'cash register' => ['admin.treasury.cash', Role::CASH_REGISTER, Role::TREASURY],
    ]);

    it('no longer opens on committee membership alone', function (string $routeName): void {
        $this->actingAs($this->plainCommittee)->get(route($routeName))->assertForbidden();
    })->with([
        'admin.treasury.transactions',
        'admin.treasury.fines',
        'admin.treasury.cash',
    ]);

    it('keeps payments readable by the committee — its baseline is read-only', function (): void {
        $this->actingAs($this->plainCommittee)->get(route('admin.treasury.payments'))->assertOk();
    });

    // The action triggers are unique markers; the "Auto-match" / "Import" wording
    // also lives in always-rendered modal titles, so we assert on the triggers.
    it('hides the reconcile and import controls from the read-only committee', function (): void {
        Livewire::actingAs($this->plainCommittee)
            ->test('pages::club-admin.treasury.payments')
            ->assertDontSee('previewBatchMatch')
            ->assertDontSee(route('admin.treasury.transactions'));
    });

    it('shows the reconcile and import controls to the treasury delegate', function (): void {
        Livewire::actingAs(User::factory()->withRole(Role::TREASURY)->create())
            ->test('pages::club-admin.treasury.payments')
            ->assertSee('previewBatchMatch')
            ->assertSee(route('admin.treasury.transactions'));
    });
});

describe('mutations are guarded inside the components too', function (): void {
    it('refuses to reconcile without the treasury delegation', function (): void {
        Livewire::actingAs($this->plainCommittee)
            ->test('pages::club-admin.treasury.payments')
            ->call('confirmBatchReconcile')
            ->assertForbidden();
    });

    it('refuses to import a bank statement without the treasury delegation', function (): void {
        $reader = User::factory()->withRole(Role::FINES)->create();

        Livewire::actingAs($reader)
            ->test('pages::club-admin.treasury.transactions')
            ->call('processImport')
            ->assertForbidden();
    });

    it('refuses to change the cash register holder without the delegation', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();

        Livewire::actingAs($treasurer)
            ->test('pages::club-admin.treasury.cash-register')
            ->call('openChangeHolder')
            ->assertForbidden();
    });
});

describe('the statutory title no longer decides', function (): void {
    it('grants nothing to a treasurer who holds no delegation', function (): void {
        $titled = User::factory()->isCommitteeMember()->create([
            'committee_role' => CommitteeRolesEnum::TREASURER,
        ]);

        expect($titled)
            ->can(Permission::PaymentsReconcile->value)->toBeFalse()
            ->can(Permission::FinesIssue->value)->toBeFalse()
            ->can(Permission::CashRegisterHolderChange->value)->toBeFalse();
    });

    it('grants the duty to someone with no title at all — the Xavier case', function (): void {
        $xavier = User::factory()->withRole(Role::CASH_REGISTER)->create();

        expect($xavier)
            ->committee_role->toBeNull()
            ->hasRole(Role::COMMITTEE->value)->toBeFalse()
            ->can(Permission::CashRegisterHolderChange->value)->toBeTrue()
            ->can(Permission::PaymentsReconcile->value)->toBeFalse();
    });
});
