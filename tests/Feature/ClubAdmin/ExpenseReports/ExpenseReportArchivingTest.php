<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsToArchiveNotification;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
});

function paidUnarchivedExpenseReport(float $amount = 20, array $attributes = [], ?string $paidOn = null): ExpenseReport
{
    $report = ExpenseReport::factory()->create(['amount' => $amount, ...$attributes]);
    (new AcceptExpenseReport)($report, User::factory()->create());
    $refund = $report->refresh()->refund;
    $debit = Transaction::create(['date' => $paidOn ?? now()->toDateString(), 'description' => 'VIREMENT', 'amount' => -$amount, 'counterparty_name' => 'X']);
    (new AllocateTransactionAction)($debit, [$refund->id => $amount]);

    return $report->refresh();
}

/*
 * Archiving is downloading the ZIP of a financial year, from the « Pièces &
 * exports » tab of the financial report — FinancialExportTest covers the
 * download itself. The expense reports screen only points there.
 */
describe('archiving from the financial report', function (): void {
    it('tells whoever may archive how many paid reports wait, year by year', function (): void {
        Carbon::setTestNow('2026-09-30 10:00:00');
        paidUnarchivedExpenseReport(10, [], '2025-11-20');
        paidUnarchivedExpenseReport(10, [], '2026-02-10');
        paidUnarchivedExpenseReport(10, [], '2026-03-10');
        paidUnarchivedExpenseReport(10, ['archived_at' => now()], '2026-04-10');

        $html = Livewire::actingAs(User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create())
            ->test('pages::club-admin.treasury.report')
            ->set('tab', 'pieces')
            ->html();
        $notice = Str::before(Str::after($html, 'data-unarchived'), '</div>');

        expect($notice)->toContain('2 en 2026')
            ->toContain('1 en 2025')
            ->toContain(e(route('admin.treasury.report', ['year' => 2025, 'tab' => 'pieces'])));
    });

    it('says nothing to a reader, whose download would archive nothing', function (): void {
        paidUnarchivedExpenseReport();

        Livewire::actingAs(User::factory()->isCommitteeMember()->create())
            ->test('pages::club-admin.treasury.report')
            ->set('tab', 'pieces')
            ->assertDontSeeHtml('data-unarchived');
    });

    it('points the expense reports screen to the report\'s export, for the year filtered', function (): void {
        Livewire::actingAs(User::factory()->isCommitteeMember()->withRole(Role::TREASURY)->create())
            ->test('pages::club-admin.treasury.expense-reports')
            ->set('fiscalYear', 2025)
            ->assertSeeHtml(route('admin.treasury.report', ['tab' => 'pieces', 'year' => 2025]))
            ->assertDontSeeHtml('wire:click="export(')
            ->assertDontSee(__('My exports'));
    });

    it('offers no link to whoever cannot open the report', function (): void {
        Livewire::actingAs(User::factory()->withRole(Role::EXPENSE_REPORTS)->create())
            ->test('pages::club-admin.treasury.expense-reports')
            ->assertDontSeeHtml('data-export-link');
    });
});

describe('the reminder to archive', function (): void {
    it('tells the deciders and the refunders how much is paid and not archived', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        $backup = User::factory()->isCommitteeMember()->withRole(Role::EXPENSE_REPORTS)->create();
        $reader = User::factory()->isCommitteeMember()->create();
        // Decides, but cannot open the financial report the ZIP comes from.
        $outsideTheReport = User::factory()->withRole(Role::EXPENSE_REPORTS)->create();
        paidUnarchivedExpenseReport(12.5);
        paidUnarchivedExpenseReport(7.5);
        paidUnarchivedExpenseReport(100, ['archived_at' => now()]);

        $this->artisan('expense-reports:remind-archiving')->assertSuccessful();

        Notification::assertSentTo([$treasurer, $backup], ExpenseReportsToArchiveNotification::class,
            fn (ExpenseReportsToArchiveNotification $n): bool => $n->count === 2 && $n->total === 20.0 && ! $n->yearEnd);
        Notification::assertNotSentTo([$reader, $outsideTheReport], ExpenseReportsToArchiveNotification::class);
    });

    it('leads to the report\'s export of the year that closed', function (): void {
        $this->travelTo('2027-01-05 08:00');
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();

        $mail = new ExpenseReportsToArchiveNotification(1, 20.0, yearEnd: true)->toMail($treasurer);

        expect($mail->actionUrl)->toBe(route('admin.treasury.report', ['tab' => 'pieces', 'year' => 2026]));
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

        expect($alerts->firstWhere('label', '1 note de frais payée à archiver')['route'] ?? null)
            ->toBe(route('admin.treasury.report', ['tab' => 'pieces']));
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
