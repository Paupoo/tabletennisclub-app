<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\Role;

describe('who may declare', function (): void {
    it('lets an adult declare', function (): void {
        expect(User::factory()->create()->can('create', ExpenseReport::class))->toBeTrue();
    });

    it('never lets a minor, nor a member whose age is unknown, declare', function (): void {
        expect(User::factory()->minor()->create()->can('create', ExpenseReport::class))->toBeFalse()
            ->and(User::factory()->create(['birthdate' => null])->can('create', ExpenseReport::class))->toBeFalse();
    });
});

describe('the new délégations', function (): void {
    it('lets the expense reports délégation decide, read the treasury, and nothing more', function (): void {
        $backup = User::factory()->withRole(Role::EXPENSE_REPORTS)->create();

        expect($backup)
            ->can(Permission::ExpenseReportsProcess->value)->toBeTrue()
            ->can(Permission::PaymentsView->value)->toBeTrue()
            ->can(Permission::PaymentsRefund->value)->toBeFalse()
            ->can(Permission::PaymentsReconcile->value)->toBeFalse();
    });

    /*
     * The deciders' bell, digest and archiving reminder link to the treasury
     * page, gated by payments.view: whoever holds a right that brings those
     * notifications must be able to open it (NotificationRoutesArchTest).
     */
    it('lets whoever decides or wires refunds read the treasury', function (Role $role): void {
        $grants = $role->permissions();

        if (in_array(Permission::ExpenseReportsProcess, $grants, true) || in_array(Permission::PaymentsRefund, $grants, true)) {
            expect($grants)->toContain(Permission::PaymentsView);
        } else {
            expect(true)->toBeTrue();
        }
    })->with(Role::cases());

    it('gives the treasury the right to decide on expense reports', function (): void {
        expect(Role::TREASURY->permissions())->toContain(Permission::ExpenseReportsProcess);
    });

    it('gives the accounts audit délégation the whole treasury to read, and nothing to write', function (): void {
        expect(array_map(static fn (Permission $p): string => $p->value, Role::ACCOUNTS_AUDIT->permissions()))
            ->toEqualCanonicalizing([
                'payments.view',
                'transactions.view',
                'fines.view',
                'cash_register.view',
                'subscriptions.view',
            ]);
    });
});

describe('who sees a report', function (): void {
    it('shows a report to its author, to the treasury readers and to nobody else', function (): void {
        $report = ExpenseReport::factory()->create();

        expect($report->user->can('view', $report))->toBeTrue()
            ->and(User::factory()->isCommitteeMember()->create()->can('view', $report))->toBeTrue()
            ->and(User::factory()->withRole(Role::ACCOUNTS_AUDIT)->create()->can('view', $report))->toBeTrue()
            ->and(User::factory()->create()->can('view', $report))->toBeFalse();
    });

    it('opens the treasury list to readers of the payments only', function (): void {
        expect(User::factory()->isCommitteeMember()->create()->can('viewAny', ExpenseReport::class))->toBeTrue()
            ->and(User::factory()->create()->can('viewAny', ExpenseReport::class))->toBeFalse();
    });

    it('shows the IBAN to its author and to whoever pays refunds only', function (): void {
        $report = ExpenseReport::factory()->create();

        expect($report->user->can('seeIban', $report))->toBeTrue()
            ->and(User::factory()->withRole(Role::TREASURY)->create()->can('seeIban', $report))->toBeTrue()
            ->and(User::factory()->withRole(Role::EXPENSE_REPORTS)->create()->can('seeIban', $report))->toBeFalse()
            ->and(User::factory()->isCommitteeMember()->create()->can('seeIban', $report))->toBeFalse();
    });
});

describe('who changes a report', function (): void {
    it('lets the author change or withdraw a report only while it is in progress', function (): void {
        $pending = ExpenseReport::factory()->create();
        $accepted = ExpenseReport::factory()->accepted()->create();

        expect($pending->user->can('update', $pending))->toBeTrue()
            ->and($pending->user->can('withdraw', $pending))->toBeTrue()
            ->and($accepted->user->can('update', $accepted))->toBeFalse()
            ->and($accepted->user->can('withdraw', $accepted))->toBeFalse()
            ->and(User::factory()->withRole(Role::TREASURY)->create()->can('update', $pending))->toBeFalse();
    });

    it('lets the author resume a rejected report only', function (): void {
        $rejected = ExpenseReport::factory()->rejected()->create();
        $pending = ExpenseReport::factory()->create();

        expect($rejected->user->can('resume', $rejected))->toBeTrue()
            ->and($pending->user->can('resume', $pending))->toBeFalse();
    });
});

describe('who decides', function (): void {
    it('lets a decider decide on somebody else\'s report in progress', function (): void {
        $report = ExpenseReport::factory()->create();
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();

        expect($treasurer->can('decide', $report))->toBeTrue()
            ->and(User::factory()->isCommitteeMember()->create()->can('decide', $report))->toBeFalse();
    });

    it('never lets a decider decide on their own report', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        $report = ExpenseReport::factory()->for($treasurer)->create();

        expect($treasurer->can('decide', $report))->toBeFalse();
    });

    it('lets a decider undo an acceptance until the money has left', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        $report = ExpenseReport::factory()->accepted()->create();
        $refund = $report->payments()->create([
            'reference' => '+++000/0000/00000+++',
            'amount_due' => $report->amount,
            'amount_paid' => 0,
            'status' => 'to_refund',
            'payment_method' => 'refund',
        ]);

        expect($treasurer->can('cancelAcceptance', $report))->toBeTrue();

        $refund->update(['amount_paid' => $report->amount, 'status' => 'refunded']);

        expect($treasurer->can('cancelAcceptance', $report->refresh()))->toBeFalse();
    });

    it('lets readers of the treasury export, not a plain member', function (): void {
        expect(User::factory()->isCommitteeMember()->create()->can('export', ExpenseReport::class))->toBeTrue()
            ->and(User::factory()->create()->can('export', ExpenseReport::class))->toBeFalse();
    });
});
