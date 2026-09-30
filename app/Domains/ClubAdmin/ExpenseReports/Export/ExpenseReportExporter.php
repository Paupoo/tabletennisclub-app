<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\ExpenseReports\Export;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Export\ProofPrinter;
use App\Domains\Competitions\Interclub\Models\Club;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use RuntimeException;
use ZipArchive;

/**
 * The expense reports' part of a financial year's export.
 *
 * Two shapes. In the PDF, for reading: one page per report with its proofs
 * printed in. In the ZIP, the archive: a summary, a spreadsheet, and every
 * proof as the member sent it, untouched — a printed copy of a receipt is
 * not the receipt. The ZIP's tree is the one the expense reports export
 * always had, under its own folder.
 *
 * A proof mPDF cannot print never fails the export: see {@see ProofPrinter}.
 */
final class ExpenseReportExporter
{
    /** A file name, stripped of what a ZIP or a file system chokes on. */
    public static function safeName(string $name): string
    {
        return (string) Str::of($name)->replaceMatches('#[/\\\\:*?"<>|]+#', '_')->limit(120, '');
    }

    /**
     * Put the reports in an archive under `$folder`: the summary, the
     * spreadsheet, and a folder of original proofs per report.
     *
     * @param  Collection<int, ExpenseReport>  $reports
     */
    public function addToZip(ZipArchive $zip, Collection $reports, string $folder): void
    {
        $disk = Storage::disk('local');

        $zip->addFromString($folder . '/recapitulatif.pdf', $this->summaryPdf($reports));
        $zip->addFromString($folder . '/notes-de-frais.csv', $this->csv($reports));

        foreach ($reports as $report) {
            $reportFolder = $folder . '/' . $this->folderName($report);
            $zip->addEmptyDir($reportFolder);

            foreach ($report->files as $file) {
                if ($disk->exists($file->path)) {
                    $zip->addFromString($reportFolder . '/' . self::safeName($file->original_name), (string) $disk->get($file->path));
                }
            }
        }
    }

    /**
     * One page per report, its proofs printed in.
     *
     * @param  Collection<int, ExpenseReport>  $reports
     */
    public function appendPages(Mpdf $mpdf, Collection $reports): void
    {
        $printer = new ProofPrinter;

        foreach ($reports as $report) {
            $row = $this->row($report);
            $printer->print($mpdf, $report->files, fn (array $images, array $unreadable): string => $this->reportHtml($row, $images, $unreadable, true, $report->files->count()));
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

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<array{name: string, src: string}>  $images
     * @param  list<string>  $unreadable
     */
    private function reportHtml(array $row, array $images, array $unreadable, bool $withProofs, int $proofCount): string
    {
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

    /**
     * The summary of the archive: the list of the reports, then a page per
     * report saying how many proofs its folder holds.
     *
     * @param  Collection<int, ExpenseReport>  $reports
     */
    private function summaryPdf(Collection $reports): string
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

        foreach ($reports as $index => $report) {
            $mpdf->AddPage();
            $mpdf->WriteHTML($this->reportHtml($rows[$index], [], [], false, $report->files->count()));
        }

        return (string) $mpdf->Output('', 'S');
    }
}
