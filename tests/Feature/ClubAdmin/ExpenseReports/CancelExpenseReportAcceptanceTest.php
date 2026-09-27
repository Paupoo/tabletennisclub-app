<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Actions\CancelExpenseReportAcceptance;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('sends an accepted report back to be decided and cancels its refund', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 20]);
    $treasurer = User::factory()->create();
    (new AcceptExpenseReport)($report, $treasurer);
    $refund = $report->refresh()->refund;

    (new CancelExpenseReportAcceptance)($report, $treasurer);

    $report->refresh();

    expect($report->status)->toBe(ExpenseReportStatus::Submitted)
        ->and($report->accepted_amount)->toBeNull()
        ->and($report->decided_by)->toBeNull()
        ->and($report->refund)->toBeNull()
        ->and($refund->refresh()->status)->toBe('cancelled');
});

it('can be accepted again afterwards, with a fresh refund', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 20]);
    $treasurer = User::factory()->create();
    (new AcceptExpenseReport)($report, $treasurer);
    (new CancelExpenseReportAcceptance)($report->refresh(), $treasurer);

    (new AcceptExpenseReport)($report->refresh(), $treasurer, 15, 'Partiel');

    expect($report->refresh()->refund->amount_due)->toBe(15.0)
        ->and($report->payments)->toHaveCount(2);
});

it('refuses once money has left the account', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 20]);
    $treasurer = User::factory()->create();
    (new AcceptExpenseReport)($report, $treasurer);
    $report->refresh()->refund->update(['amount_paid' => 20, 'status' => 'refunded']);

    (new CancelExpenseReportAcceptance)($report->refresh(), $treasurer);
})->throws(DomainException::class);

it('refuses on a report that is not accepted', function (): void {
    $report = ExpenseReport::factory()->create();

    (new CancelExpenseReportAcceptance)($report, User::factory()->create());
})->throws(DomainException::class);

it('never lets anyone undo a decision on their own report', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 20]);
    (new AcceptExpenseReport)($report, User::factory()->create());

    (new CancelExpenseReportAcceptance)($report->refresh(), $report->user);
})->throws(DomainException::class);
