<?php

declare(strict_types=1);

namespace App\Domains\ClubAdmin\Finance\Services;

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Shared\Enums\FinancialExportScope;
use App\Domains\Shared\Enums\MovementClosure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What stands behind the figures of a financial year: its supporting
 * documents, the expense reports it paid, its website payments, and the
 * journal of its movements.
 *
 * The « Pièces & exports » tab lists exactly what the export will hold, so
 * both read them here, the export narrowed to a poste and to the pieces
 * asked for.
 *
 * A document belongs to the year it is dated in, and also to the year that
 * paid it: an invoice of December settled in January is a piece of both, and
 * the auditor of January must find what the journal points to.
 */
final readonly class YearPieces
{
    /**
     * @param  list<array<string, mixed>>  $journal  {@see FinancialReport::journal()}
     */
    private function __construct(
        private FinancialReport $report,
        private array $journal,
        private ?string $poste,
        private FinancialExportScope $scope,
    ) {}

    /**
     * @param  string|null  $poste  `expense:hall`, `income:subsidies`… null for every poste.
     */
    public static function of(FinancialReport $report, ?string $poste = null, FinancialExportScope $scope = FinancialExportScope::All): self
    {
        $journal = $report->journal();

        if ($poste !== null) {
            $journal = array_values(array_filter($journal, static fn (array $row): bool => in_array($poste, $row['postes'], true)));
        }

        return new self($report, $journal, $poste, $scope);
    }

    /**
     * The supporting documents of the year, oldest first, with their files.
     *
     * @return Collection<int, SupportingDocument>
     */
    public function documents(): Collection
    {
        if (! $this->scope->includesDocuments()) {
            return new Collection;
        }

        $year = $this->report->year();
        $from = $year->start()->toDateString();
        $until = $year->end()->toDateString();

        return SupportingDocument::query()
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $dated): Builder => $dated->datedIn($year))
                ->orWhereHas('transactions', fn (Builder $line): Builder => $line->whereDate('date', '>=', $from)->whereDate('date', '<=', $until))
                ->orWhereHas('cashRegisterEntries', fn (Builder $entry): Builder => $entry->whereDate('cash_register_entries.created_at', '>=', $from)->whereDate('cash_register_entries.created_at', '<=', $until)))
            ->when($this->poste !== null, fn (Builder $query): Builder => $query->inCategory((string) $this->poste))
            ->with(['transactions', 'cashRegisterEntries', 'files'])
            ->orderBy('date')
            ->orderBy('supporting_documents.id')
            ->get();
    }

    /**
     * The expense reports whose refund left the account in the year, with
     * the day it left and how much.
     *
     * @return list<array{date: CarbonImmutable, report: ExpenseReport, amount: float}>
     */
    public function expenseReports(): array
    {
        if (! $this->scope->includesExpenseReports()) {
            return [];
        }

        $paid = [];

        foreach ($this->journal as $row) {
            foreach ($row['expense_reports'] as $id) {
                $paid[$id] = ['date' => $row['date'], 'amount' => -$row['amount']];
            }
        }

        if ($paid === []) {
            return [];
        }

        return ExpenseReport::query()
            ->whereKey(array_keys($paid))
            ->when($this->poste !== null, fn (Builder $query): Builder => $query->where('category', str_starts_with((string) $this->poste, 'expense:') ? substr((string) $this->poste, 8) : ''))
            ->with(['user', 'files', 'refund.credits.transaction', 'decider'])
            ->orderBy('id')
            ->get()
            ->map(fn (ExpenseReport $report): array => ['report' => $report, ...$paid[$report->id]])
            ->sortBy(fn (array $row): string => $row['date']->toDateString() . sprintf('-%010d', $row['report']->id))
            ->values()
            ->all();
    }

    /**
     * Every movement of the year, narrowed to the poste when one was asked.
     *
     * @return list<array<string, mixed>>
     */
    public function journal(): array
    {
        return $this->journal;
    }

    /**
     * The movements the website reconciled, expense report refunds aside —
     * those are listed with their reports.
     *
     * @return list<array<string, mixed>>
     */
    public function sitePayments(): array
    {
        return array_values(array_filter(
            $this->journal,
            static fn (array $row): bool => $row['closure'] === MovementClosure::Reconciled && $row['expense_reports'] === [],
        ));
    }
}
