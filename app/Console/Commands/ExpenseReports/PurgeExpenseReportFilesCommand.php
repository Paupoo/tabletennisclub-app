<?php

declare(strict_types=1);

namespace App\Console\Commands\ExpenseReports;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the proofs of reports the club never paid, two years on.
 *
 * A rejected or withdrawn report is no accounting record — no money left —
 * and a receipt can carry a name, an address, the end of a card number. The
 * report itself stays, reason and all; only its files go.
 *
 * Accepted and paid reports are never touched: their proofs back the accounts
 * and the law has them kept for years. A purge that got that wrong would
 * destroy evidence, so it simply does not look at them.
 */
#[Signature('expense-reports:purge-files {--years=2 : How long the proofs of unpaid reports are kept}')]
#[Description('Delete the proofs of expense reports rejected or withdrawn long ago, keeping their record')]
class PurgeExpenseReportFilesCommand extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subYears(max(1, (int) $this->option('years')));
        $purged = 0;

        ExpenseReport::query()
            ->with('files')
            ->whereIn('status', [ExpenseReportStatus::Rejected, ExpenseReportStatus::Withdrawn])
            ->whereNull('files_purged_at')
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->each(function (ExpenseReport $report) use (&$purged): void {
                Storage::disk('local')->delete($report->files->pluck('path')->all());
                $report->files()->delete();
                $report->update(['files_purged_at' => now()]);
                $purged++;
            });

        $this->components->info(trans_choice('{0}No proof to purge.|[1,*]Proofs purged on :count report(s).', $purged, ['count' => $purged]));

        return self::SUCCESS;
    }
}
