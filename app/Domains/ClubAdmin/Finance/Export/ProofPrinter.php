<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Export;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportFile;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfNormaliser;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocumentFile;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Throwable;

/**
 * Prints a piece — an expense report, a supporting document — on a page of
 * its own, its proofs printed in: the images under the facts, the pages of a
 * PDF proof after them.
 *
 * A proof mPDF cannot print (a broken image, a PDF its parser refuses) never
 * fails the export: the page says so and points to the original.
 */
final class ProofPrinter
{
    /**
     * @param  iterable<ExpenseReportFile|SupportingDocumentFile>  $files
     * @param  Closure(list<array{name: string, src: string}>, list<string>): string  $page  The piece's page, given the images to print and the names of the files it could not.
     */
    public function print(Mpdf $mpdf, iterable $files, Closure $page): void
    {
        $disk = Storage::disk('local');
        $images = [];
        $pdfs = [];
        $unreadable = [];

        foreach ($files as $file) {
            if (! $disk->exists($file->path)) {
                $unreadable[] = $file->original_name;
            } elseif ($file->isImage()) {
                $images[] = ['name' => $file->original_name, 'src' => 'data:' . $file->mime_type . ';base64,' . base64_encode((string) $disk->get($file->path))];
            } elseif ($file->isPdf()) {
                $pdfs[] = $file;
            }
        }

        $mpdf->AddPage();

        try {
            $mpdf->WriteHTML($page($images, $unreadable));
        } catch (Throwable) {
            // An image mPDF cannot decode: the page is printed again
            // without it, and says where the original is.
            $mpdf->WriteHTML($page([], [...$unreadable, ...array_column($images, 'name')]));
        }

        foreach ($pdfs as $file) {
            if (! $this->importPdf($mpdf, $disk->path($file->path))) {
                $mpdf->WriteHTML(View::make('expense-reports.export-unreadable', ['unreadable' => [$file->original_name]])->render());
            }
        }
    }

    private function appendPdfPages(Mpdf $mpdf, string $source): void
    {
        $pages = $mpdf->setSourceFile($source);

        for ($page = 1; $page <= $pages; $page++) {
            $template = $mpdf->importPage($page);
            $mpdf->AddPage();
            $mpdf->useTemplate($template, 0, 0, 210);
        }
    }

    /**
     * Prints the pages of a PDF proof, going through Ghostscript once when
     * the parser refuses the file as it came — the same way the attestation
     * templates are made readable.
     */
    private function importPdf(Mpdf $mpdf, string $source): bool
    {
        try {
            $this->appendPdfPages($mpdf, $source);

            return true;
        } catch (Throwable) {
            $normaliser = new PdfNormaliser;

            if (! $normaliser->isAvailable()) {
                return false;
            }

            $converted = storage_path('app/mpdf/proof-' . Str::uuid() . '.pdf');

            try {
                $this->appendPdfPages($mpdf, $normaliser->toPdf14($source, $converted));

                return true;
            } catch (Throwable) {
                return false;
            } finally {
                @unlink($converted);
            }
        }
    }
}
