<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportAcceptedNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('accepts the declared amount and opens a refund to the frozen IBAN', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 42.5, 'refund_iban' => 'BE68539007547034']);
    $treasurer = User::factory()->create();

    (new AcceptExpenseReport)($report, $treasurer);

    $report->refresh();
    $refund = $report->refund;

    expect($report->status)->toBe(ExpenseReportStatus::Accepted)
        ->and($report->displayStatus())->toBe(ExpenseReportDisplayStatus::Accepted)
        ->and($report->accepted_amount)->toBe(42.5)
        ->and($report->decided_by)->toBe($treasurer->id)
        ->and($report->decided_at)->not->toBeNull()
        ->and($refund)->not->toBeNull()
        ->and($refund->payment_method)->toBe('refund')
        ->and($refund->status)->toBe('to_refund')
        ->and($refund->amount_due)->toBe(42.5)
        ->and($refund->amount_paid)->toBe(0.0)
        ->and($refund->refund_iban)->toBe('BE68539007547034');

    Notification::assertSentTo($report->user, ExpenseReportAcceptedNotification::class);
});

it('accepts a lower amount when a reason says why', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 42.5]);

    (new AcceptExpenseReport)($report, User::factory()->create(), 34.5, 'Gourde personnelle exclue');

    $report->refresh();

    expect($report->accepted_amount)->toBe(34.5)
        ->and($report->amount)->toBe(42.5)
        ->and($report->decision_reason)->toBe('Gourde personnelle exclue')
        ->and($report->refund->amount_due)->toBe(34.5);
});

it('refuses a lower amount without a reason', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 42.5]);

    (new AcceptExpenseReport)($report, User::factory()->create(), 34.5);
})->throws(DomainException::class);

it('never accepts more than was declared', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 42.5]);

    (new AcceptExpenseReport)($report, User::factory()->create(), 45.0, 'Faute de frappe');
})->throws(DomainException::class);

it('refuses a zero amount', function (): void {
    $report = ExpenseReport::factory()->create(['amount' => 42.5]);

    (new AcceptExpenseReport)($report, User::factory()->create(), 0.0, 'Rien');
})->throws(DomainException::class);

it('never lets anyone accept their own report', function (): void {
    $report = ExpenseReport::factory()->create();

    (new AcceptExpenseReport)($report, $report->user);
})->throws(DomainException::class);

it('only accepts a report still waiting for a decision', function (string $state): void {
    $report = ExpenseReport::factory()->{$state}()->create();

    (new AcceptExpenseReport)($report, User::factory()->create());
})->with(['rejected', 'withdrawn', 'accepted'])->throws(DomainException::class);
