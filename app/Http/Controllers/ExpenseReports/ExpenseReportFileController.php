<?php

declare(strict_types=1);

namespace App\Http\Controllers\ExpenseReports;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportFile;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a proof to whoever may read its report.
 *
 * The files sit on the private disk — a receipt can carry a name, an address,
 * the end of a card number — so they only ever leave through here, once the
 * policy has spoken. Inline by default, so the decider's drawer can show them;
 * `?download=1` hands the original over as a file.
 */
class ExpenseReportFileController extends Controller
{
    public function show(Request $request, ExpenseReportFile $file): StreamedResponse
    {
        $this->authorize('view', $file->expenseReport);

        abort_unless(Storage::disk('local')->exists($file->path), 404);

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($file->path, $file->original_name);
        }

        return Storage::disk('local')->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            // A proof is shown, never run: an SVG or HTML slipped past the
            // upload rules would otherwise execute under the application's origin.
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
