<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Services\FinancialPosition;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\MovementClosure;
use App\Domains\Shared\ValueObjects\FiscalYear;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Rapport financier »: a financial year's accounts, for those who answer
 * for them at the general assembly — the committee, the accounts auditors
 * and the treasury.
 *
 * Read-only: nothing on this screen writes. Every figure comes from
 * {@see FinancialReport} (the flows of the year, compared with the year
 * before) and {@see FinancialPosition} (what is owed and held); the screen
 * only lays them out. The PDF of the general assembly reads the same two.
 *
 * The financial year is navigation, not a filter (DS-A): the page is about
 * exactly one year and titles itself with it.
 */
new class extends Component
{
    use HasBreadcrumbs;

    /**
     * The calendar year the shown financial year starts in, see {@see FiscalYear::startingIn()}.
     */
    #[Url(as: 'year')]
    public ?int $fiscalYear = null;

    #[Url]
    public string $tab = 'overview';

    public function mount(): void
    {
        $this->fiscalYear ??= FiscalYear::current()->startYear();
    }

    /**
     * What the year's documents, expense reports and website payments are —
     * the « Pièces & exports » tab.
     *
     * @return array{documents: Collection<int, SupportingDocument>, expenseReports: list<array{date: CarbonImmutable, report: ExpenseReport, amount: float}>, sitePayments: list<array<string, mixed>>}
     */
    #[Computed]
    public function pieces(): array
    {
        $journal = $this->report->journal();

        $reportDates = [];

        foreach ($journal as $row) {
            foreach ($row['expense_reports'] as $id) {
                $reportDates[$id] = ['date' => $row['date'], 'amount' => -$row['amount']];
            }
        }

        $reports = Feature::ExpenseReports->enabled() && $reportDates !== []
            ? ExpenseReport::query()->whereKey(array_keys($reportDates))->with('user')->orderBy('id')->get()
            : new Collection;

        return [
            'documents' => SupportingDocument::query()
                ->datedIn($this->year())
                ->with(['transactions', 'cashRegisterEntries'])
                ->orderBy('date')
                ->orderBy('supporting_documents.id')
                ->get(),
            'expenseReports' => $reports
                ->map(fn (ExpenseReport $report): array => ['report' => $report, ...$reportDates[$report->id]])
                ->sortBy('date')
                ->values()
                ->all(),
            'sitePayments' => array_values(array_filter(
                $journal,
                static fn (array $row): bool => $row['closure'] === MovementClosure::Reconciled && $row['expense_reports'] === [],
            )),
        ];
    }

    #[Computed]
    public function position(): FinancialPosition
    {
        return new FinancialPosition;
    }

    #[Computed]
    public function previousReport(): FinancialReport
    {
        return FinancialReport::for($this->year()->previous());
    }

    public function render(): View
    {
        $report = $this->report;
        $previous = $this->previousReport;
        $position = $this->position;
        $year = $this->year();
        $treasuryDay = CarbonImmutable::today()->min($year->end());

        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'yearLabel' => $year->label(),
            'previousLabel' => $year->previous()->label(),
            'yearOptions' => collect(range(FiscalYear::current()->startYear(), $this->firstYear()))
                ->map(fn (int $start): array => ['id' => $start, 'name' => FiscalYear::startingIn($start)->label()])
                ->all(),
            'flows' => [
                'income' => [$report->income(), $previous->income()],
                'expenses' => [$report->expenses(), $previous->expenses()],
                'result' => [$report->result(), $previous->result()],
            ],
            'justification' => $report->justification(),
            'members' => $position->membersWithOpenDebt(),
            'receivables' => $position->openReceivables(),
            'debts' => $position->openDebts(),
            'treasury' => $position->treasuryAt($treasuryDay),
            'treasuryDay' => $treasuryDay,
            'months' => array_map(fn (array $month): array => [
                'label' => $month['month']->translatedFormat('M'),
                'long' => ucfirst($month['month']->translatedFormat('F Y')),
                'income' => $month['income'],
                'expenses' => $month['expenses'],
                'cumulative' => $month['cumulative'],
            ], $report->monthly()),
            'expenseRows' => $this->posteRows($report->expensesByCategory(), $previous->expensesByCategory(), 'expense'),
            'incomeRows' => $this->posteRows($report->incomeByCategory(), $previous->incomeByCategory(), 'income'),
            'trainings' => [
                'income' => [$report->incomeByCategory()[IncomeCategory::Trainings->value] ?? 0.0, $previous->incomeByCategory()[IncomeCategory::Trainings->value] ?? 0.0],
                'expense' => [$report->expensesByCategory()[ExpenseCategory::Training->value] ?? 0.0, $previous->expensesByCategory()[ExpenseCategory::Training->value] ?? 0.0],
            ],
            'fees' => [$report->membershipFeesByLicence(), $previous->membershipFeesByLicence()],
            'internalMovements' => $report->internalMovements(),
        ]);
    }

    #[Computed]
    public function report(): FinancialReport
    {
        return FinancialReport::for($this->year());
    }

    public function updatedFiscalYear(): void
    {
        unset($this->report, $this->previousReport, $this->pieces);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Financial report'));
    }

    /**
     * The oldest year worth offering: the first one any money moved in.
     */
    private function firstYear(): int
    {
        $oldest = collect([
            DB::table('transactions')->min('date'),
            DB::table('cash_register_entries')->min('created_at'),
        ])->filter()->min();

        return min(FiscalYear::current()->startYear(), $oldest === null ? PHP_INT_MAX : FiscalYear::for(CarbonImmutable::parse($oldest))->startYear());
    }

    /**
     * One row per poste either year saw, largest this year first,
     * uncategorised last whatever its size — it is a to-do, not a poste.
     *
     * @param  array<string, float>  $current
     * @param  array<string, float>  $previous
     * @return list<array{key: string, label: string, current: float, previous: float, uncategorised: bool}>
     */
    private function posteRows(array $current, array $previous, string $direction): array
    {
        $rows = [];

        foreach (array_unique([...array_keys($current), ...array_keys($previous)]) as $key) {
            $category = $direction === 'expense' ? ExpenseCategory::tryFrom($key) : IncomeCategory::tryFrom($key);

            $rows[] = [
                'key' => $key,
                'label' => $category?->label() ?? __('Uncategorised — to process'),
                'current' => $current[$key] ?? 0.0,
                'previous' => $previous[$key] ?? 0.0,
                'uncategorised' => $key === FinancialReport::UNCATEGORISED,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$a['uncategorised'], -$a['current'], -$a['previous']] <=> [$b['uncategorised'], -$b['current'], -$b['previous']]);

        return $rows;
    }

    private function year(): FiscalYear
    {
        return $this->fiscalYear === null ? FiscalYear::current() : FiscalYear::startingIn($this->fiscalYear);
    }
};
