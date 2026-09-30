<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Jobs;

use App\Domains\ClubAdmin\Finance\Export\FinancialExporter;
use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use App\Domains\ClubAdmin\Finance\Notifications\FinancialExportFailedNotification;
use App\Domains\ClubAdmin\Finance\Notifications\FinancialExportReadyNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Builds an export away from the request: a year of receipts can take longer
 * to gather than a page is allowed to wait.
 *
 * Once built, the export records the expense reports it really holds: those
 * are the ones a ZIP download archives.
 */
class GenerateFinancialExport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(public int $exportId) {}

    public function failed(?Throwable $exception): void
    {
        $export = FinancialExport::with('requester')->find($this->exportId);

        // A failure that lands after the file was built (a worker killed on
        // its way out, say) must not throw away an export already handed out.
        if ($export === null || $export->status !== 'pending') {
            return;
        }

        $export->update(['status' => 'failed']);

        $export->requester?->notify(new FinancialExportFailedNotification($export));
    }

    public function handle(): void
    {
        $export = FinancialExport::with('requester')->find($this->exportId);

        if ($export === null || $export->status !== 'pending') {
            return;
        }

        $exporter = new FinancialExporter($export);
        $path = $exporter->build();

        $export->update([
            'status' => 'ready',
            'path' => $path,
            'report_ids' => $exporter->reportIds(),
            'expires_at' => now()->addDays(FinancialExport::KEPT_FOR_DAYS),
        ]);

        // The file is built and downloadable from the tab: a notice that
        // cannot leave (mail down, a worker older than the download route) is
        // reported, never a reason to mark the export failed.
        rescue(fn () => $export->requester->notify(new FinancialExportReadyNotification($export)));
    }
}
