<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Export;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportFile;
use App\Domains\ClubAdmin\Subscriptions\Attestations\Pdf\PdfNormaliser;
use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Turns a set of expense reports into what the treasurer prints or archives.
 *
 * Two shapes. The PDF is for reading: a summary, then one page per report
 * with its proofs printed in. The ZIP is the archive: the same summary, a
 * spreadsheet, and every proof as the member sent it, untouched — a printed
 * copy of a receipt is not the receipt.
 *
 * A proof mPDF cannot print (a broken image, a PDF its parser refuses) never
 * fails the export: the page says so and points to the original.
 */
final class ExpenseReportExporter
{
    private const string DIRECTORY = 'expense-report-exports';

    /**
     * @param  Collection<int, ExpenseReport>  $reports
     * @return string The path of the file on the private disk.
     */
    public function pdf(Collection $reports, int $exportId): string
    {
        $path = self::DIRECTORY . "/{$exportId}/notes-de-frais.pdf";
        Storage::disk('local')->put($path, $this->renderPdf($reports, withProofs: true));

        return $path;
    }

    /**
     * @param  Collection<int, ExpenseReport>  $reports
     * @return string The path of the file on the private disk.
     */
    public function zip(Collection $reports, int $exportId): string
    {
        $path = self::DIRECTORY . "/{$exportId}/notes-de-frais.zip";
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        $zip = new ZipArchive;

        if ($zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the expense reports archive.');
        }

        $zip->addFromString('recapitulatif.pdf', $this->renderPdf($reports, withProofs: false));
        $zip->addFromString('notes-de-frais.csv', $this->csv($reports));

        foreach ($reports as $report) {
            $folder = $this->folderName($report);
            $zip->addEmptyDir($folder);

            foreach ($report->files as $file) {
                if ($disk->exists($file->path)) {
                    $zip->addFromString($folder . '/' . $this->safeName($file->original_name), (string) $disk->get($file->path));
                }
            }
        }

        $zip->close();

        return $path;
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
     * The spreadsheet: semicolons and decimal commas, as a Belgian Excel
     * opens it, with a byte-order mark so the accents survive.
     *
     * @param  Collection<int, ExpenseReport>  $reports
     */
    private function csv(Collection $reports): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not open a buffer for the spreadsheet.');
        }

        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, [
            '#', __('Member'), __('Nature'), __('Description'), __('Date of the expense'), __('Declared on'),
            __('Declared amount'), __('Accepted amount'), __('Status'), __('Decided by'), __('Decided on'),
            __('Reason sent to the member'), __('Refund reference'), __('Paid on'), __('Archived on'),
        ], ';', '"', '');

        foreach ($reports as $report) {
            $row = $this->row($report);
            fputcsv($handle, [
                $row['id'], $row['member'], $row['category'], $row['description'], $row['spent_on_iso'], $row['declared_on'],
                $row['declared_amount'], $row['accepted_amount'], $row['status'], $row['decided_by'], $row['decided_at'],
                $row['reason'], $row['refund_reference'], $row['paid_on_iso'], $row['archived_on'],
            ], ';', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * `2026-09-12_Dupont-Jean_42,50€_#123`: sorts by date in any file
     * browser, and says whose, how much and which report without opening it.
     */
    private function folderName(ExpenseReport $report): string
    {
        $name = Str::of($report->user->last_name . ' ' . $report->user->first_name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9]+/', '-')
            ->trim('-');

        return sprintf(
            '%s_%s_%s€_#%d',
            $report->spent_on->toDateString(),
            $name,
            number_format((float) ($report->accepted_amount ?? $report->amount), 2, ',', ''),
            $report->id,
        );
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

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }

    /**
     * @param  Collection<int, ExpenseReport>  $reports
     */
    private function renderPdf(Collection $reports, bool $withProofs): string
    {
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'tempDir' => storage_path('app/mpdf'),
        ]);
        $mpdf->SetTitle(__('Expense reports'));

        $rows = $reports->map(fn (ExpenseReport $report): array => $this->row($report));

        $mpdf->WriteHTML(View::make('expense-reports.export-summary', [
            'clubName' => Club::ourClub()->value('name') ?? config('app.name'),
            'reports' => $reports,
            'rows' => $rows,
            'total' => $this->money($reports->sum(fn (ExpenseReport $r): float => (float) ($r->accepted_amount ?? $r->amount))),
            'byCategory' => $reports->groupBy(fn (ExpenseReport $r): string => $r->category->label())
                ->map(fn (Collection $group): string => $this->money($group->sum(fn (ExpenseReport $r): float => (float) ($r->accepted_amount ?? $r->amount))))
                ->sortKeys()
                ->all(),
            'exportedAt' => now(),
            'exportedBy' => Auth::user()?->full_name ?? '—',
        ])->render());

        $disk = Storage::disk('local');

        foreach ($reports as $index => $report) {
            $images = [];
            $pdfs = [];
            $unreadable = [];

            foreach ($withProofs ? $report->files : [] as $file) {
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
                $mpdf->WriteHTML($this->reportHtml($rows[$index], $images, $unreadable, $withProofs, $report->files->count()));
            } catch (Throwable) {
                // An image mPDF cannot decode: the page is printed again
                // without it, and says where the original is.
                $mpdf->WriteHTML($this->reportHtml($rows[$index], [], [...$unreadable, ...array_column($images, 'name')], $withProofs, $report->files->count()));
            }

            foreach ($pdfs as $file) {
                /** @var ExpenseReportFile $file */
                if (! $this->importPdf($mpdf, $disk->path($file->path))) {
                    $mpdf->WriteHTML($this->reportHtml(null, [], [$file->original_name], true, 0));
                }
            }
        }

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * @param  array<string, mixed>|null  $row
     * @param  list<array{name: string, src: string}>  $images
     * @param  list<string>  $unreadable
     */
    private function reportHtml(?array $row, array $images, array $unreadable, bool $withProofs, int $proofCount): string
    {
        if ($row === null) {
            return View::make('expense-reports.export-unreadable', ['unreadable' => $unreadable])->render();
        }

        return View::make('expense-reports.export-report', [
            'row' => $row,
            'images' => $images,
            'unreadable' => $unreadable,
            'withProofs' => $withProofs,
            'proofCount' => $proofCount,
        ])->render();
    }

    /**
     * Everything a line of the summary, the spreadsheet or a report page says.
     *
     * @return array<string, mixed>
     */
    private function row(ExpenseReport $report): array
    {
        $paidOn = $report->paidOn();

        return [
            'id' => $report->id,
            'member' => $report->user->full_name,
            'category' => $report->category->label(),
            'description' => $report->description,
            'spent_on' => $report->spent_on->format('d/m/Y'),
            'spent_on_iso' => $report->spent_on->toDateString(),
            'declared_on' => $report->created_at?->format('d/m/Y') ?? '',
            'declared_amount' => $this->money($report->amount),
            'accepted_amount' => $report->accepted_amount === null ? '' : $this->money($report->accepted_amount),
            'amount' => $this->money((float) ($report->accepted_amount ?? $report->amount)),
            'status' => $report->displayStatus()->label(),
            'decided_by' => $report->decider?->full_name ?? '',
            'decided_at' => $report->decided_at?->format('d/m/Y H:i') ?? '',
            'reason' => (string) $report->decision_reason,
            'refund_reference' => $report->refund?->reference ?? '',
            'paid_on' => $paidOn?->format('d/m/Y') ?? '',
            'paid_on_iso' => $paidOn?->toDateString() ?? '',
            'archived_on' => $report->archived_at?->format('d/m/Y') ?? '',
        ];
    }

    /** A member's file name, stripped of what a ZIP or a file system chokes on. */
    private function safeName(string $name): string
    {
        return (string) Str::of($name)->replaceMatches('#[/\\\\:*?"<>|]+#', '_')->limit(120, '');
    }
}
