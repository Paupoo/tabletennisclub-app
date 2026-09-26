<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Jobs;

use App\Domains\ClubAdmin\ExpenseReports\Export\ExpenseReportExporter;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use App\Domains\ClubAdmin\ExpenseReports\Notifications\ExpenseReportExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Builds an export away from the request: a season of receipts can take
 * longer to gather than a page is allowed to wait.
 */
class GenerateExpenseReportExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $exportId) {}

    public function failed(?Throwable $exception): void
    {
        ExpenseReportExport::whereKey($this->exportId)->update(['status' => 'failed']);
    }

    public function handle(): void
    {
        $export = ExpenseReportExport::with('requester')->find($this->exportId);

        if ($export === null || $export->status !== 'pending') {
            return;
        }

        $reports = ExpenseReport::with(['user', 'files', 'refund.credits.transaction', 'decider'])
            ->whereKey($export->report_ids)
            ->orderBy('spent_on')
            ->orderBy('id')
            ->get();

        $exporter = new ExpenseReportExporter;
        $path = $export->isZip() ? $exporter->zip($reports, $export->id) : $exporter->pdf($reports, $export->id);

        $export->update([
            'status' => 'ready',
            'path' => $path,
            'expires_at' => now()->addDays(ExpenseReportExport::KEPT_FOR_DAYS),
        ]);

        $export->requester->notify(new ExpenseReportExportReadyNotification($export));
    }
}
