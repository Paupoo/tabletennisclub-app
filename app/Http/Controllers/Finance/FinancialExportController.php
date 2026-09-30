<?php

declare(strict_types=1);

namespace App\Http\Controllers\Finance;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands an export to the one who asked for it.
 *
 * Downloading a ZIP is also what archives the expense reports it holds: once
 * a decider or whoever wires refunds has the originals on their own machine,
 * they are no longer kept in one place only. A committee member exporting
 * out of curiosity, or a PDF — a printed copy, not the receipts — archives
 * nothing.
 */
class FinancialExportController extends Controller
{
    public function download(FinancialExport $export): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();

        abort_unless($export->requested_by === $user->id, 403);
        abort_if($export->isExpired(), 410, __('This export has expired: run it again from the financial report.'));
        abort_unless($export->status === 'ready' && $export->path !== null && Storage::disk('local')->exists($export->path), 404);

        if ($export->isZip() && $export->report_ids !== [] && $user->can('archive', ExpenseReport::class)) {
            ExpenseReport::whereKey($export->report_ids)
                ->whereNull('archived_at')
                ->update(['archived_at' => now()]);
        }

        return Storage::disk('local')->download($export->path, $export->downloadName());
    }
}
