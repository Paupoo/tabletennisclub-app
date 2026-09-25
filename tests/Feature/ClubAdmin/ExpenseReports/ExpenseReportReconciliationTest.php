<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportPaidNotification;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Payment\Notifications\WeeklyRefundReminderNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\Role;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
});

function acceptedReport(float $amount = 42.5): ExpenseReport
{
    $report = ExpenseReport::factory()->create(['amount' => $amount]);
    (new AcceptExpenseReport)($report, User::factory()->create());

    return $report->refresh();
}

function debit(float $amount): Transaction
{
    return Transaction::create([
        'date' => now()->toDateString(),
        'description' => 'VIREMENT EUROPEEN',
        'amount' => -$amount,
        'counterparty_name' => 'Membre',
    ]);
}

it('becomes paid once the bank debit is reconciled with its refund, and tells the member', function (): void {
    $report = acceptedReport(42.5);

    (new AllocateTransactionAction)(debit(42.5), [$report->refund->id => 42.5]);

    $report->refresh();

    expect($report->displayStatus())->toBe(ExpenseReportDisplayStatus::Paid);
    Notification::assertSentTo($report->user, ExpenseReportPaidNotification::class);
});

it('stays accepted while only part of the refund has left', function (): void {
    $report = acceptedReport(42.5);

    (new AllocateTransactionAction)(debit(20), [$report->refund->id => 20]);

    expect($report->refresh()->displayStatus())->toBe(ExpenseReportDisplayStatus::Accepted);
    Notification::assertNotSentTo($report->user, ExpenseReportPaidNotification::class);
});

it('leaves expense report refunds alone when the payments screen cancels refunds in bulk', function (): void {
    $report = acceptedReport();
    $refund = $report->refund;
    $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('selected', [(string) $refund->id])
        ->call('bulkCancelRefund');

    expect($refund->refresh()->status)->toBe('to_refund')
        ->and($report->refresh()->displayStatus())->toBe(ExpenseReportDisplayStatus::Accepted);
});

it('lists expense report refunds in the treasury refunds, named after the member', function (): void {
    $first = acceptedReport(10);
    $second = acceptedReport(20);
    $treasurer = User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create();

    Livewire::actingAs($treasurer)
        ->test('pages::club-admin.treasury.payments')
        ->set('statusFilter', 'to_refund')
        ->set('eventType', ExpenseReport::class)
        ->assertSee($first->user->full_name)
        ->assertSee($second->user->full_name)
        ->assertSee(__('Expense report'));
});

it('asks the weekly refund reminder to wire to the account frozen on the report', function (): void {
    $member = User::factory()->create(['iban' => 'BE71096123456769']);
    $report = ExpenseReport::factory()->for($member)->create(['amount' => 12, 'refund_iban' => 'BE68539007547034']);
    (new AcceptExpenseReport)($report, User::factory()->create());
    $treasurer = User::factory()->withRole(Role::TREASURY)->create();

    $this->artisan('payment:send-refund-reminder')->assertSuccessful();

    Notification::assertSentTo($treasurer, WeeklyRefundReminderNotification::class, function (WeeklyRefundReminderNotification $notification) use ($treasurer): bool {
        $mail = implode("\n", $notification->toMail($treasurer)->introLines);

        return str_contains($mail, 'BE68 5390 0754 7034') && ! str_contains($mail, 'BE71 0961 2345 6769');
    });
});
