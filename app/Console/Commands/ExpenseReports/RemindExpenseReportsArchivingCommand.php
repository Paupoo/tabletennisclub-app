<?php

declare(strict_types=1);

namespace App\Console\Commands\ExpenseReports;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportsToArchiveNotification;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\Permission;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Reminds whoever may archive that paid reports are still kept in one place.
 *
 * Only the deciders and whoever wires refunds: their ZIP download is what
 * archives, so reminding a reader would ask for a gesture that counts for
 * nothing.
 */
#[Signature('expense-reports:remind-archiving {--year-end : The financial year has just closed}')]
#[Description('Remind the treasury of paid expense reports not archived yet')]
class RemindExpenseReportsArchivingCommand extends Command
{
    public function handle(): int
    {
        $unarchived = ExpenseReport::query()
            ->whereDisplayStatus(ExpenseReportDisplayStatus::Paid)
            ->whereNull('archived_at');

        $count = (clone $unarchived)->count();

        if ($count === 0) {
            $this->components->info('Every paid expense report is archived.');

            return self::SUCCESS;
        }

        $total = round((int) (clone $unarchived)->sum('accepted_amount') / 100, 2);

        $recipients = User::permission([
            Permission::ExpenseReportsProcess->value,
            Permission::PaymentsRefund->value,
        ])->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new ExpenseReportsToArchiveNotification($count, $total, (bool) $this->option('year-end')));
        }

        $this->components->info("Archiving reminder sent to {$recipients->count()} recipient(s) for {$count} report(s).");

        return self::SUCCESS;
    }
}
