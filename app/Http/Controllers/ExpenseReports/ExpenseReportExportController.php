<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExpenseReports;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Hands an export to the one who asked for it.
 *
 * Downloading a ZIP is also what archives: once a decider or whoever wires
 * refunds has the originals on their own machine, the reports in it are no
 * longer kept in one place only. A committee member exporting out of
 * curiosity, or a PDF — a printed copy, not the receipts — archives nothing.
 */
class ExpenseReportExportController extends Controller
{
    public function download(ExpenseReportExport $export): StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();

        abort_unless($export->requested_by === $user->id, 403);
        abort_if($export->isExpired(), 410, __('This export has expired: run it again from the expense reports.'));
        abort_unless($export->status === 'ready' && $export->path !== null && Storage::disk('local')->exists($export->path), 404);

        if ($export->isZip() && $user->canAny([Permission::ExpenseReportsProcess->value, Permission::PaymentsRefund->value])) {
            ExpenseReport::whereKey($export->report_ids)
                ->whereNull('archived_at')
                ->update(['archived_at' => now()]);
        }

        return Storage::disk('local')->download($export->path, basename($export->path, '.' . $export->format) . '-' . $export->created_at?->format('Y-m-d') . '.' . $export->format);
    }
}
