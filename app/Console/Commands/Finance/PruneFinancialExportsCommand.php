<?php

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the export files a week after they were made.
 *
 * An export gathers the receipts of many members in one file; kept on the
 * disk it would only double what the documents and reports already hold. The
 * row stays, so the link can say the file has expired instead of pretending
 * it never was.
 */
#[Signature('financial-exports:prune')]
#[Description('Delete the financial export files past their week')]
class PruneFinancialExportsCommand extends Command
{
    public function handle(): int
    {
        $pruned = 0;

        FinancialExport::query()
            ->where('status', '!=', 'expired')
            ->where(fn ($query) => $query->where('expires_at', '<', now())
                // A job that died leaves no expiry: a day is plenty to finish.
                ->orWhere(fn ($query) => $query->whereNull('expires_at')->where('created_at', '<', now()->subDay())))
            ->orderBy('id')
            ->each(function (FinancialExport $export) use (&$pruned): void {
                $disk = Storage::disk('local');
                $disk->deleteDirectory(FinancialExport::DIRECTORY . '/' . $export->id);

                // An expense reports export made before the financial report
                // lives in the folder it was written to.
                if ($export->path !== null) {
                    $disk->deleteDirectory(dirname($export->path));
                }

                $export->update(['status' => 'expired', 'path' => null]);
                $pruned++;
            });

        $this->components->info(trans_choice('{0}No export to prune.|[1,*]:count export(s) pruned.', $pruned, ['count' => $pruned]));

        return self::SUCCESS;
    }
}
