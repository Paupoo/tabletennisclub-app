<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf;

use Mpdf\Mpdf;

/**
 * Marks a page as a rehearsal, so it can never pass for the real thing.
 *
 * The office has no other way to see what its seal looks like on an insurer's
 * form, and a preview that looked exactly like a real attestation would end up
 * in an envelope sooner or later. mPDF's own watermark is drawn before the
 * page content, which an imported template covers entirely — so it is written
 * here, over everything, once the form and its values are down.
 */
final readonly class SpecimenWatermark
{
    private const string LABEL = 'SPÉCIMEN';

    public function stamp(Mpdf $mpdf, float $pageWidth, float $pageHeight): void
    {
        $mpdf->SetFont('dejavusans', 'B', 46);
        $mpdf->SetTextColor(220, 38, 38);
        $mpdf->SetAlpha(0.18);

        // Repeated down the page rather than a single band: a form is a dense
        // page, and one word across the middle leaves whole blocks that read as
        // an ordinary document when the page is cropped or photographed.
        for ($row = 1; $row <= 4; $row++) {
            $y = $pageHeight * $row / 5;

            $mpdf->StartTransform();
            $mpdf->transformRotate(35, $pageWidth / 2, $y);
            $mpdf->Text($pageWidth * 0.12, $y, self::LABEL);
            $mpdf->StopTransform();
        }

        $mpdf->SetAlpha(1);
        $mpdf->SetTextColor(0, 0, 0);
    }
}
