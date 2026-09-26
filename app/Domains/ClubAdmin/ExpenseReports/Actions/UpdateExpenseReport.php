<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Actions;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportFile;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class UpdateExpenseReport
{
    /**
     * Correct a report nobody has decided on yet. Every change lands in the
     * audit log, so the decider can see what the member changed and when.
     *
     * @param  array<int, UploadedFile>  $newFiles
     * @param  array<int, int>  $removedFileIds
     */
    public function __invoke(
        ExpenseReport $report,
        ExpenseCategory $category,
        string $description,
        float $amount,
        CarbonInterface $spentOn,
        string $refundIban,
        array $newFiles = [],
        array $removedFileIds = [],
    ): void {
        if ($report->status !== ExpenseReportStatus::Submitted) {
            throw new DomainException('Only a report still in progress can be changed.');
        }

        $removed = $report->files()->whereKey($removedFileIds)->get();

        if ($report->files()->count() - $removed->count() + count($newFiles) < 1) {
            throw new DomainException('An expense report needs at least one proof.');
        }

        DB::transaction(function () use ($report, $category, $description, $amount, $spentOn, $refundIban, $removed): void {
            $report->update([
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'spent_on' => $spentOn,
                'refund_iban' => $refundIban,
            ]);

            $removed->each(fn (ExpenseReportFile $file): ?bool => $file->delete());
        });

        Storage::disk('local')->delete($removed->pluck('path')->all());

        (new StoreExpenseReportFiles)($report, $newFiles);
    }
}
