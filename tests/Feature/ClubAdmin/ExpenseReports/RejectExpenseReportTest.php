<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\RejectExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportRejectedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('rejects a report with the reason the member will read', function (): void {
    $report = ExpenseReport::factory()->create();
    $treasurer = User::factory()->create();

    (new RejectExpenseReport)($report, $treasurer, 'Le ticket est illisible.');

    $report->refresh();

    expect($report->status)->toBe(ExpenseReportStatus::Rejected)
        ->and($report->decision_reason)->toBe('Le ticket est illisible.')
        ->and($report->decided_by)->toBe($treasurer->id)
        ->and($report->payments)->toBeEmpty();

    Notification::assertSentTo($report->user, ExpenseReportRejectedNotification::class);
});

it('refuses to reject without a reason', function (): void {
    $report = ExpenseReport::factory()->create();

    (new RejectExpenseReport)($report, User::factory()->create(), '   ');
})->throws(DomainException::class);

it('never lets anyone reject their own report', function (): void {
    $report = ExpenseReport::factory()->create();

    (new RejectExpenseReport)($report, $report->user, 'Doublon');
})->throws(DomainException::class);

it('only rejects a report still waiting for a decision', function (): void {
    $report = ExpenseReport::factory()->accepted()->create();

    (new RejectExpenseReport)($report, User::factory()->create(), 'Trop tard');
})->throws(DomainException::class);
