<?php

declare(strict_types=1);

namespace App\Http\Controllers\ClubEvents\Meeting;

use App\Domains\Meetings\Models\Meeting;
use App\Domains\Meetings\Pdf\MinutesPdf;
use App\Domains\Meetings\Services\MinutesReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The minutes as a PDF, rendered now: the route's `can:readMinutes` decides who.
 */
final class MeetingMinutesPdfController extends Controller
{
    public function __invoke(Request $request, Meeting $meeting, MinutesPdf $pdf): Response
    {
        $report = MinutesReport::for($meeting, $request->user());

        return response($pdf->render($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $report->pdfFilename() . '"',
        ]);
    }
}
