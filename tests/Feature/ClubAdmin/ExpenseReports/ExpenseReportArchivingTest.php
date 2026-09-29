<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Jobs\GenerateExpenseReportExport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsToArchiveNotification;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
});

function paidUnarchivedExpenseReport(float $amount = 20, array $attributes = []): ExpenseReport
{
    $report = ExpenseReport::factory()->create(['amount' => $amount, ...$attributes]);
    (new AcceptExpenseReport)($report, User::factory()->create());
    $refund = $report->refresh()->refund;
    $debit = Transaction::create(['date' => now()->toDateString(), 'description' => 'VIREMENT', 'amount' => -$amount, 'counterparty_name' => 'X']);
    (new AllocateTransactionAction)($debit, [$refund->id => $amount]);

    return $report->refresh();
}

describe('archiving from the screen', function (): void {
    it('exports as a ZIP every paid report not archived yet, in one click', function (): void {
        Queue::fake();
        $toArchive = paidUnarchivedExpenseReport();
        paidUnarchivedExpenseReport(10, ['archived_at' => now()]);
        ExpenseReport::factory()->create();

        Livewire::actingAs(User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create())
            ->test('pages::club-admin.treasury.expense-reports')
            ->call('archiveUnarchived');

        $export = ExpenseReportExport::sole();

        expect($export->format)->toBe('zip')
            ->and($export->report_ids)->toBe([$toArchive->id]);
        Queue::assertPushed(GenerateExpenseReportExport::class);
    });

    it('is not offered to a reader, whose download would archive nothing', function (): void {
        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::club-admin.treasury.expense-reports')
            ->assertDontSeeHtml('wire:click="archiveUnarchived"')
            ->call('archiveUnarchived')
            ->assertForbidden();
    });
});

describe('the reminder to archive', function (): void {
    it('tells the deciders and the refunders how much is paid and not archived', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        $backup = User::factory()->withRole(Role::EXPENSE_REPORTS)->create();
        $reader = User::factory()->isCommitteeMember()->create();
        paidUnarchivedExpenseReport(12.5);
        paidUnarchivedExpenseReport(7.5);
        paidUnarchivedExpenseReport(100, ['archived_at' => now()]);

        $this->artisan('expense-reports:remind-archiving')->assertSuccessful();

        Notification::assertSentTo([$treasurer, $backup], ExpenseReportsToArchiveNotification::class,
            fn (ExpenseReportsToArchiveNotification $n): bool => $n->count === 2 && $n->total === 20.0 && ! $n->yearEnd);
        Notification::assertNotSentTo($reader, ExpenseReportsToArchiveNotification::class);
    });

    it('speaks of the closed financial year in January', function (): void {
        $this->travelTo('2027-01-05 08:00');
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        paidUnarchivedExpenseReport();

        $this->artisan('expense-reports:remind-archiving', ['--year-end' => true])->assertSuccessful();

        Notification::assertSentTo($treasurer, ExpenseReportsToArchiveNotification::class, fn (ExpenseReportsToArchiveNotification $n): bool => $n->yearEnd && str_contains((string) $n->toMail($treasurer)->subject, 'Exercice 2026 clôturé'));
    });

    it('names a closed financial year that straddles two calendar years', function (): void {
        Club::factory()->ownClub()->create(['fiscal_year_start_month' => 9]);
        $this->travelTo('2026-09-05 08:00');
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        paidUnarchivedExpenseReport();

        $this->artisan('expense-reports:remind-archiving', ['--year-end' => true])->assertSuccessful();

        Notification::assertSentTo($treasurer, ExpenseReportsToArchiveNotification::class, fn (ExpenseReportsToArchiveNotification $n): bool => str_contains((string) $n->toMail($treasurer)->subject, 'Exercice 2025-2026 clôturé'));
    });

    it('stays silent when everything paid is archived', function (): void {
        User::factory()->withRole(Role::TREASURY)->create();
        paidUnarchivedExpenseReport(10, ['archived_at' => now()]);

        $this->artisan('expense-reports:remind-archiving')->assertSuccessful();

        Notification::assertSentTimes(ExpenseReportsToArchiveNotification::class, 0);
    });

    it('runs each quarter, and on the fifth of the month after the year closes', function (string $at, ?string $expected): void {
        $this->travelTo($at);

        expect(archivingRemindersDueNow())->toBe($expected === null ? [] : [$expected]);
    })->with([
        'a calendar year closes' => ['2027-01-05 08:00', 'expense-reports:remind-archiving --year-end'],
        'the first quarter ends' => ['2026-04-01 08:00', 'expense-reports:remind-archiving'],
        'the third quarter ends' => ['2026-10-01 08:00', 'expense-reports:remind-archiving'],
        'the year-end reminder already covers January' => ['2027-01-01 08:00', null],
        'any other month' => ['2026-02-05 08:00', null],
    ]);

    it('follows a financial year that starts in September', function (string $at, ?string $expected): void {
        Club::factory()->ownClub()->create(['fiscal_year_start_month' => 9]);
        $this->travelTo($at);

        expect(archivingRemindersDueNow())->toBe($expected === null ? [] : [$expected]);
    })->with([
        'the year closes in August' => ['2026-09-05 08:00', 'expense-reports:remind-archiving --year-end'],
        'the first quarter ends in November' => ['2026-12-01 08:00', 'expense-reports:remind-archiving'],
        'January is no longer a closing' => ['2027-01-05 08:00', null],
        'October is no longer a quarter' => ['2026-10-01 08:00', null],
    ]);

    it('stays off when expense reports are switched off', function (): void {
        $this->travelTo('2027-01-05 08:00');
        config(['features.expense_reports' => false]);

        expect(archivingRemindersDueNow())->toBe([]);
    });

    it('shows the dashboard alert to whoever may archive', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        paidUnarchivedExpenseReport();

        $alerts = collect($this->actingAs($treasurer)->get(route('dashboard'))->viewData('alerts'));

        expect($alerts->pluck('label'))->toContain('1 note de frais payée à archiver');
    });
});

/**
 * The archiving reminders the scheduler would start at this very minute.
 *
 * @return array<int, string>
 */
function archivingRemindersDueNow(): array
{
    return collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'expense-reports:remind-archiving'))
        ->filter(fn ($event): bool => $event->isDue(app()) && $event->filtersPass(app()))
        ->map(fn ($event): string => trim(Str::after((string) $event->command, "'artisan'")))
        ->values()
        ->all();
}
