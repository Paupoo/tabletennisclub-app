<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Export;

use App\Domains\ClubAdmin\ExpenseReports\Export\ExpenseReportExporter;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use App\Domains\ClubAdmin\Finance\Services\FinancialPosition;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Finance\Services\FinancialReportFigures;
use App\Domains\ClubAdmin\Finance\Services\YearPieces;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Shared\Enums\FinancialExportScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use RuntimeException;
use ZipArchive;

/**
 * Turns a financial year into what the general assembly reads and what the
 * club keeps.
 *
 * The PDF is for reading: the journal of every movement, then a page per
 * supporting document and per expense report with its proofs printed in. The
 * ZIP is the archive: the journal as a spreadsheet, and every original file
 * untouched — `pieces/P-2026-0042 — AFTT/…` and the expense reports' tree
 * under `notes-de-frais/`, as their export always laid it out.
 *
 * The report (tiles, charts, postes) is already on the overview tab: it only
 * opens the PDF, or joins the ZIP as `rapport-financier.pdf`, when asked for.
 */
final class FinancialExporter
{
    /** The expense reports the last file built holds — the ZIP download archives those. */
    private array $reportIds = [];

    public function __construct(private readonly FinancialExport $export) {}

    /**
     * Build the file and say where it is on the private disk.
     */
    public function build(): string
    {
        $year = $this->export->year() ?? throw new RuntimeException('An export names the financial year it covers.');
        $report = FinancialReport::for($year);
        $pieces = YearPieces::of($report, $this->export->poste, $this->export->scope);

        $expenseReports = new Collection(array_column($pieces->expenseReports(), 'report'));
        $this->reportIds = $expenseReports->modelKeys();

        $path = FinancialExport::DIRECTORY . '/' . $this->export->id . '/' . $this->export->downloadName();
        $disk = Storage::disk('local');
        $disk->makeDirectory(dirname($path));

        if ($this->export->isZip()) {
            $this->writeZip($disk->path($path), $report, $pieces, $expenseReports);
        } else {
            $disk->put($path, $this->pdf($report, $pieces, $expenseReports, withReport: $this->export->include_report, withPieces: true));
        }

        return $path;
    }

    /**
     * @return list<int>
     */
    public function reportIds(): array
    {
        return array_values($this->reportIds);
    }

    /**
     * The journal as a Belgian Excel opens it: semicolons, decimal commas,
     * ISO dates, and a byte-order mark so the accents survive — the same
     * conventions as the expense reports' spreadsheet.
     *
     * @param  list<array<string, mixed>>  $journal
     */
    private function csv(array $journal): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new RuntimeException('Could not open a buffer for the spreadsheet.');
        }

        fwrite($handle, "\u{FEFF}");
        fputcsv($handle, [
            __('Date'), __('Account or till'), __('Statement'), __('Counterparty'), __('Description'),
            __('Amount'), __('Poste'), __('State'), __('Supporting documents'), __('Expense reports'),
        ], ';', '"', '');

        foreach ($journal as $row) {
            fputcsv($handle, [
                $row['date']->toDateString(),
                $row['source'],
                (string) $row['statement'],
                (string) $row['counterparty'],
                $row['description'],
                number_format($row['amount'], 2, ',', ''),
                implode(', ', array_map(FinancialReport::posteLabel(...), $row['postes'])),
                $row['closure']->label(),
                implode(', ', $row['documents']),
                implode(', ', array_map(static fn (int $id): string => '#' . $id, $row['expense_reports'])),
            ], ';', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * `P-2026-0042 — AFTT`: the reference sorts, the counterparty says whose.
     */
    private function documentFolder(SupportingDocument $document): string
    {
        return $document->reference() . ' — ' . ExpenseReportExporter::safeName((string) $document->counterparty);
    }

    /**
     * What each movement linked to a document was, for its page.
     *
     * @return list<string>
     */
    private function documentMovements(SupportingDocument $document): array
    {
        $money = static fn (float $euros): string => number_format($euros, 2, ',', ' ') . ' €';

        return [
            ...$document->transactions->map(fn (Transaction $line): string => $line->date->format('d/m/Y') . ' — ' . __('Bank') . ' — ' . $money((float) $line->amount) . ' — ' . Str::limit((string) $line->description, 60))->all(),
            ...$document->cashRegisterEntries->map(fn (CashRegisterEntry $entry): string => $entry->created_at?->format('d/m/Y') . ' — ' . __('Cash register') . ' — ' . $money($entry->amount / 100) . ' — ' . Str::limit((string) $entry->reason, 60))->all(),
        ];
    }

    /**
     * What the export was narrowed to, printed under the title.
     *
     * @return list<string>
     */
    private function filters(): array
    {
        return array_values(array_filter([
            $this->export->poste === null ? null : __('Poste: :poste', ['poste' => FinancialReport::posteLabel($this->export->poste)]),
            $this->export->scope === FinancialExportScope::All ? null : $this->export->scope->label(),
        ]));
    }

    private function newPdf(): Mpdf
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
        $mpdf->SetTitle(__('Financial report') . ' ' . $this->export->year()?->label());

        return $mpdf;
    }

    /**
     * @param  Collection<int, ExpenseReport>  $expenseReports
     */
    private function pdf(FinancialReport $report, YearPieces $pieces, Collection $expenseReports, bool $withReport, bool $withPieces): string
    {
        $mpdf = $this->newPdf();
        $meta = [
            'clubName' => Club::ourClub()->value('name') ?? config('app.name'),
            'exportedAt' => now(),
            'exportedBy' => $this->export->requester->full_name,
            'filters' => $this->filters(),
        ];
        $figures = FinancialReportFigures::of($report, FinancialReport::previousFor($report->year()), new FinancialPosition);

        if ($withReport) {
            $mpdf->WriteHTML(View::make('financial-export.report', [...$figures, ...$meta])->render());
        }

        // Without the report, the journal opens the file and carries the
        // heading the report would have: whose accounts, when, by whom.
        $heading = $withReport ? [] : $meta;

        // The journal goes in slices: mPDF's parser chokes on a single
        // string of several hundred rows.
        foreach (array_chunk($pieces->journal(), 150) as $index => $rows) {
            if ($withReport || $index > 0) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML(View::make('financial-export.journal', [
                'journal' => $rows,
                'yearLabel' => $figures['yearLabel'],
                'filters' => $index === 0 ? $meta['filters'] : [],
                'heading' => $index === 0 ? $heading : [],
            ])->render());
        }

        if ($pieces->journal() === []) {
            if ($withReport) {
                $mpdf->AddPage();
            }

            $mpdf->WriteHTML(View::make('financial-export.journal', ['journal' => [], 'yearLabel' => $figures['yearLabel'], 'filters' => $meta['filters'], 'heading' => $heading])->render());
        }

        if ($withPieces) {
            $printer = new ProofPrinter;

            foreach ($pieces->documents() as $document) {
                $movements = $this->documentMovements($document);
                $printer->print($mpdf, $document->files, fn (array $images, array $unreadable): string => View::make('financial-export.document', [
                    'document' => $document,
                    'movements' => $movements,
                    'images' => $images,
                    'unreadable' => $unreadable,
                ])->render());
            }

            (new ExpenseReportExporter)->appendPages($mpdf, $expenseReports);
        }

        return (string) $mpdf->Output('', 'S');
    }

    /**
     * @param  Collection<int, ExpenseReport>  $expenseReports
     */
    private function writeZip(string $absolutePath, FinancialReport $report, YearPieces $pieces, Collection $expenseReports): void
    {
        $zip = new ZipArchive;

        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the financial export archive.');
        }

        $disk = Storage::disk('local');

        if ($this->export->include_report) {
            $zip->addFromString('rapport-financier.pdf', $this->pdf($report, $pieces, $expenseReports, withReport: true, withPieces: false));
        }

        $zip->addFromString('journal.csv', $this->csv($pieces->journal()));

        foreach ($pieces->documents() as $document) {
            $folder = 'pieces/' . $this->documentFolder($document);
            $zip->addEmptyDir($folder);

            foreach ($document->files as $file) {
                if ($disk->exists($file->path)) {
                    $zip->addFromString($folder . '/' . ExpenseReportExporter::safeName($file->original_name), (string) $disk->get($file->path));
                }
            }
        }

        if ($expenseReports->isNotEmpty()) {
            (new ExpenseReportExporter)->addToZip($zip, $expenseReports, 'notes-de-frais');
        }

        $zip->close();
    }
}
