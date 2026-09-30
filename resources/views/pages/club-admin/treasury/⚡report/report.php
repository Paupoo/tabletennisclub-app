<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Finance\Jobs\GenerateFinancialExport;
use App\Domains\ClubAdmin\Finance\Models\FinancialExport;
use App\Domains\ClubAdmin\Finance\Services\FinancialPosition;
use App\Domains\ClubAdmin\Finance\Services\FinancialReport;
use App\Domains\ClubAdmin\Finance\Services\FinancialReportFigures;
use App\Domains\ClubAdmin\Finance\Services\YearPieces;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\FinancialExportScope;
use App\Domains\Shared\Enums\IncomeCategory;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\ValueObjects\FiscalYear;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * « Rapport financier »: a financial year's accounts, for those who answer
 * for them at the general assembly — the committee, the accounts auditors
 * and the treasury.
 *
 * Read-only, but for asking for an export of the year — which anyone who
 * reads the report may do, each call checking it. Every figure comes from
 * {@see FinancialReport} (the flows of the year, compared with the year
 * before) and {@see FinancialPosition} (what is owed and held); the screen
 * only lays them out. The PDF of the general assembly reads the same two.
 *
 * The financial year is navigation, not a filter (DS-A): the page is about
 * exactly one year and titles itself with it.
 */
new class extends Component
{
    use HasBreadcrumbs, Toast;

    /** `expense:hall`, `income:subsidies`… empty for every poste. */
    public string $exportPoste = '';

    public string $exportScope = 'all';

    /**
     * The calendar year the shown financial year starts in, see {@see FiscalYear::startingIn()}.
     */
    #[Url(as: 'year')]
    public ?int $fiscalYear = null;

    #[Url]
    public string $tab = 'overview';

    /**
     * Queue the export of the year shown — a PDF to read, or a ZIP of the
     * originals — narrowed to a poste and to the pieces asked for, and tell
     * the requester when it is ready.
     */
    public function export(string $format): void
    {
        Gate::authorize(Permission::FinancialReportView->value);

        if (! in_array($format, ['pdf', 'zip'], true)) {
            return;
        }

        $this->validate([
            'exportPoste' => ['nullable', Rule::in(array_column($this->posteOptions(), 'id'))],
            'exportScope' => ['required', Rule::in(array_map(static fn (FinancialExportScope $scope): string => $scope->value, FinancialExportScope::offered()))],
        ]);

        $this->queueExport($format, $this->year()->startYear(), $this->exportPoste === '' ? null : $this->exportPoste, FinancialExportScope::from($this->exportScope));
    }

    public function mount(): void
    {
        $this->fiscalYear ??= FiscalYear::current()->startYear();
    }

    /**
     * The requester's own exports still worth showing: those being built, and
     * those finished within the week a file is kept. The tab polls while one
     * is being built, so « ready » appears without a reload.
     *
     * @return Collection<int, FinancialExport>
     */
    #[Computed]
    public function myExports(): Collection
    {
        return FinancialExport::query()
            ->where('requested_by', Auth::id())
            ->where('created_at', '>=', now()->subDays(FinancialExport::KEPT_FOR_DAYS))
            ->latest()
            ->orderByDesc('id')
            ->limit(5)
            ->get();
    }

    /**
     * What stands behind the year's figures — the « Pièces & exports » tab,
     * which is what the export holds, see {@see YearPieces}.
     */
    #[Computed]
    public function pieces(): YearPieces
    {
        return YearPieces::of($this->report);
    }

    #[Computed]
    public function position(): FinancialPosition
    {
        return new FinancialPosition;
    }

    /**
     * Every poste an export can be narrowed to, expenses then income.
     *
     * @return list<array{id: string, name: string}>
     */
    public function posteOptions(): array
    {
        return [
            ...array_map(static fn (ExpenseCategory $category): array => ['id' => 'expense:' . $category->value, 'name' => __('Expense') . ' — ' . $category->label()], ExpenseCategory::ordered()),
            ...array_map(static fn (IncomeCategory $category): array => ['id' => 'income:' . $category->value, 'name' => __('Income') . ' — ' . $category->label()], IncomeCategory::ordered()),
        ];
    }

    /**
     * The year before, whole once the shown year has closed, up to the same
     * day while it runs — see {@see FinancialReport::previousFor()}.
     */
    #[Computed]
    public function previousReport(): FinancialReport
    {
        return FinancialReport::previousFor($this->year());
    }

    public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'yearOptions' => collect(range(FiscalYear::current()->startYear(), $this->firstYear()))
                ->map(fn (int $start): array => ['id' => $start, 'name' => FiscalYear::startingIn($start)->label()])
                ->all(),
            ...FinancialReportFigures::of($this->report, $this->previousReport, $this->position),
        ]);
    }

    #[Computed]
    public function report(): FinancialReport
    {
        return FinancialReport::for($this->year());
    }

    /**
     * Build again an export that failed, with the same year, poste and pieces.
     */
    public function retryExport(int $exportId): void
    {
        Gate::authorize(Permission::FinancialReportView->value);

        $export = FinancialExport::query()
            ->where('requested_by', Auth::id())
            ->where('status', 'failed')
            ->whereNotNull('fiscal_year')
            ->find($exportId);

        abort_if($export === null, 404);

        $this->queueExport($export->format, (int) $export->fiscal_year, $export->poste, $export->scope);
    }

    /**
     * The paid expense reports nobody archived yet, counted per financial
     * year — for whoever's ZIP download archives them. Their year is the one
     * the refund left the account in, as the export files them.
     *
     * @return array<int, int> count keyed by the year's start year, oldest first
     */
    #[Computed]
    public function unarchivedByYear(): array
    {
        if (! Feature::ExpenseReports->enabled() || Gate::denies('archive', ExpenseReport::class)) {
            return [];
        }

        return ExpenseReport::query()
            ->whereDisplayStatus(ExpenseReportDisplayStatus::Paid)
            ->whereNull('archived_at')
            ->with('refund')
            ->orderBy('id')
            ->get()
            ->map(fn (ExpenseReport $report): ?int => ($paidOn = $report->paidOn()) === null ? null : FiscalYear::for($paidOn)->startYear())
            ->filter()
            ->countBy()
            ->sortKeys()
            ->all();
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

    private function queueExport(string $format, int $fiscalYear, ?string $poste, FinancialExportScope $scope): void
    {
        $export = FinancialExport::create([
            'requested_by' => Auth::id(),
            'format' => $format,
            'fiscal_year' => $fiscalYear,
            'poste' => $poste,
            'scope' => $scope,
            'report_ids' => [],
            'status' => 'pending',
        ]);

        GenerateFinancialExport::dispatch($export->id);

        unset($this->myExports);
        $this->success(__('The export is being prepared. You will get an email with the link when it is ready.'));
    }

    private function year(): FiscalYear
    {
        return $this->fiscalYear === null ? FiscalYear::current() : FiscalYear::startingIn($this->fiscalYear);
    }
};
