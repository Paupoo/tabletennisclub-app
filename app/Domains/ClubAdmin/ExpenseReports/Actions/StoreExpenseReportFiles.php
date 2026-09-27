<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use Illuminate\Http\UploadedFile;

final class StoreExpenseReportFiles
{
    /**
     * Put proofs on the private disk, one folder per report, and fingerprint
     * each so the same receipt sent twice can be told apart from two receipts.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function __invoke(ExpenseReport $report, array $files): void
    {
        foreach ($files as $file) {
            $report->files()->create([
                'path' => $file->store("expense-reports/{$report->id}", 'local'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'sha256' => (string) hash_file('sha256', $file->getRealPath()),
            ]);
        }
    }
}
