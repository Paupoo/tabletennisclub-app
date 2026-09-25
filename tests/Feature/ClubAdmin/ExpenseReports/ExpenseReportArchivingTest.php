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
use App\Domains\Shared\Enums\Role;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function (): void {
    Notification::fake();
});

function paidAndUnarchived(float $amount = 20, array $attributes = []): ExpenseReport
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
        $toArchive = paidAndUnarchived();
        paidAndUnarchived(10, ['archived_at' => now()]);
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
        paidAndUnarchived(12.5);
        paidAndUnarchived(7.5);
        paidAndUnarchived(100, ['archived_at' => now()]);

        $this->artisan('expense-reports:remind-archiving')->assertSuccessful();

        Notification::assertSentTo([$treasurer, $backup], ExpenseReportsToArchiveNotification::class,
            fn (ExpenseReportsToArchiveNotification $n): bool => $n->count === 2 && $n->total === 20.0 && ! $n->yearEnd);
        Notification::assertNotSentTo($reader, ExpenseReportsToArchiveNotification::class);
    });

    it('speaks of the closed financial year in January', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        paidAndUnarchived();

        $this->artisan('expense-reports:remind-archiving', ['--year-end' => true])->assertSuccessful();

        Notification::assertSentTo($treasurer, ExpenseReportsToArchiveNotification::class, fn (ExpenseReportsToArchiveNotification $n): bool => $n->yearEnd && str_contains((string) $n->toMail($treasurer)->subject, (string) (now()->year - 1)));
    });

    it('stays silent when everything paid is archived', function (): void {
        User::factory()->withRole(Role::TREASURY)->create();
        paidAndUnarchived(10, ['archived_at' => now()]);

        $this->artisan('expense-reports:remind-archiving')->assertSuccessful();

        Notification::assertSentTimes(ExpenseReportsToArchiveNotification::class, 0);
    });

    it('runs on the first of each quarter, and on the fifth of January for the closed year', function (): void {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'expense-reports:remind-archiving'));

        expect($events->map(fn ($event): string => $event->expression)->sort()->values()->all())
            ->toBe(['0 8 1 4,7,10 *', '0 8 5 1 *'])
            ->and($events->first(fn ($event): bool => $event->expression === '0 8 5 1 *')->command)->toContain('--year-end');

        config(['features.expense_reports' => false]);

        expect($events->every(fn ($event): bool => ! $event->filtersPass(app())))->toBeTrue();
    });

    it('shows the dashboard alert to whoever may archive', function (): void {
        $treasurer = User::factory()->withRole(Role::TREASURY)->create();
        paidAndUnarchived();

        $alerts = collect($this->actingAs($treasurer)->get(route('dashboard'))->viewData('alerts'));

        expect($alerts->pluck('label'))->toContain('1 note de frais payée à archiver');
    });
});
