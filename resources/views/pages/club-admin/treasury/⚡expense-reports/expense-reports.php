<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\AcceptExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Actions\CancelExpenseReportAcceptance;
use App\Domains\ClubAdmin\ExpenseReports\Actions\RejectExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Jobs\GenerateExpenseReportExport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReportExport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Enums\ExpenseReportStatus;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * Every member's expense reports, and where the treasury decides on them.
 *
 * Read by whoever reads the treasury — the committee and the accounts
 * auditors included — and decided on by whoever holds the right, never on
 * one's own report. The policy says both; this screen only asks it.
 */
new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast, WithPagination;

    public string $acceptedAmount = '';

    public bool $acceptModal = false;

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    #[Url(as: 'from')]
    public string $dateFrom = '';

    #[Url(as: 'to')]
    public string $dateTo = '';

    public string $decisionReason = '';

    #[Url(as: 'year')]
    public ?int $fiscalYear = null;

    public bool $readerDrawer = false;

    public bool $rejectModal = false;

    #[Url]
    public string $search = '';

    #[Url(as: 'report')]
    public ?int $shownId = null;

    #[Url(as: 'tab')]
    public string $statusFilter = 'submitted';

    #[Url(as: 'unarchived')]
    public bool $unarchivedOnly = false;

    #[Url(as: 'member')]
    public ?int $userId = null;

    /** The quarterly gesture: a ZIP of every paid report not archived yet. */
    public function archiveUnarchived(): void
    {
        Gate::authorize('archive', ExpenseReport::class);

        $ids = ExpenseReport::query()
            ->whereDisplayStatus(ExpenseReportDisplayStatus::Paid)
            ->whereNull('archived_at')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->queueExport('zip', $ids);
    }

    public function cancelAcceptance(): void
    {
        $report = $this->shownOrFail();
        Gate::authorize('cancelAcceptance', $report);

        try {
            (new CancelExpenseReportAcceptance)($report, $this->actor());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->refreshLists();
        $this->success(__('Acceptance undone: the refund is cancelled and the report waits for a decision again.'));
    }

    public function clearFilters(): void
    {
        $this->reset(['categoryFilter', 'dateFrom', 'dateTo', 'fiscalYear', 'unarchivedOnly', 'userId']);
        $this->resetPage();
    }

    public function confirmAccept(): void
    {
        $report = $this->shownOrFail();
        Gate::authorize('decide', $report);

        $this->acceptedAmount = str_replace([',', ' ', '€'], ['.', '', ''], $this->acceptedAmount);
        $isReduced = is_numeric($this->acceptedAmount) && round((float) $this->acceptedAmount, 2) < $report->amount;

        $this->validate([
            'acceptedAmount' => ['required', 'numeric', 'min:0.01', 'max:' . $report->amount],
            'decisionReason' => [$isReduced ? 'required' : 'nullable', 'string', 'max:1000'],
        ], [
            'acceptedAmount.max' => __('Never more than the declared :amount €.', ['amount' => number_format($report->amount, 2, ',', ' ')]),
            'decisionReason.required' => __('Say why the amount is lower: the member will read it.'),
        ]);

        (new AcceptExpenseReport)($report, $this->actor(), round((float) $this->acceptedAmount, 2), $this->decisionReason);

        $this->acceptModal = false;
        $this->refreshLists();
        $this->success(__('Accepted: the refund is waiting in the payments to refund.'));
    }

    public function confirmReject(): void
    {
        $report = $this->shownOrFail();
        Gate::authorize('decide', $report);

        $this->validate(['decisionReason' => ['required', 'string', 'max:1000']], [
            'decisionReason.required' => __('A rejection always says why: the member will read it.'),
        ]);

        (new RejectExpenseReport)($report, $this->actor(), $this->decisionReason);

        $this->rejectModal = false;
        $this->refreshLists();
        $this->success(__('Rejected. The member has been told why.'));
    }

    /**
     * Queue a PDF or ZIP of exactly what the screen shows — the tab, the
     * search and the filters — and tell the requester when it is ready.
     */
    public function export(string $format): void
    {
        Gate::authorize('export', ExpenseReport::class);

        if (! in_array($format, ['pdf', 'zip'], true)) {
            return;
        }

        $this->queueExport($format, $this->filteredQuery()->orderBy('expense_reports.id')->pluck('expense_reports.id')->all());
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->userId !== null ? ['key' => 'userId', 'label' => User::find($this->userId)?->full_name ?? (string) $this->userId] : null,
            $this->categoryFilter !== '' ? ['key' => 'categoryFilter', 'label' => ExpenseCategory::tryFrom($this->categoryFilter)?->label() ?? $this->categoryFilter] : null,
            $this->fiscalYear !== null ? ['key' => 'fiscalYear', 'label' => __('Financial year :year', ['year' => $this->fiscalYear])] : null,
            $this->dateFrom !== '' ? ['key' => 'dateFrom', 'label' => __('Spent from :date', ['date' => $this->dateFrom])] : null,
            $this->dateTo !== '' ? ['key' => 'dateTo', 'label' => __('Spent until :date', ['date' => $this->dateTo])] : null,
            $this->unarchivedOnly ? ['key' => 'unarchivedOnly', 'label' => __('Not archived yet')] : null,
        ]));
    }

    /**
     * @return array<int, array{key: string, label: string, sortable?: bool}>
     */
    public function headers(): array
    {
        return [
            ['key' => 'member', 'label' => __('Member'), 'sortable' => false],
            ['key' => 'description', 'label' => __('Expense'), 'sortable' => false],
            ['key' => 'spent_on', 'label' => __('Spent on'), 'sortable' => false],
            ['key' => 'amount', 'label' => __('Amount'), 'sortable' => false],
            ['key' => 'created_at', 'label' => __('Submitted'), 'sortable' => false],
            ['key' => 'status', 'label' => __('Status'), 'sortable' => false],
        ];
    }

    /**
     * The IBAN as this reader may see it: whole for the author and whoever
     * wires refunds, its last four digits for everyone else.
     */
    public function ibanFor(ExpenseReport $report): string
    {
        if (Gate::allows('seeIban', $report)) {
            return (string) IbanNormalizer::format($report->refund_iban);
        }

        return substr((string) $report->refund_iban, 0, 2) . ' •••• •••• ' . substr((string) $report->refund_iban, -4);
    }

    /**
     * The members who ever declared something, for the member filter.
     *
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function memberOptions(): array
    {
        $members = User::query()
            ->whereIn('id', ExpenseReport::query()->select('user_id'))
            ->get(['id', 'first_name', 'last_name']);

        return LocaleSort::by($members, fn (User $user): string => $user->full_name)
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->full_name])
            ->values()
            ->all();
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', ExpenseReport::class);

        if ($this->shownId !== null) {
            $this->show($this->shownId);
        }
    }

    public function openAccept(): void
    {
        $report = $this->shownOrFail();
        Gate::authorize('decide', $report);

        $this->resetValidation();
        $this->acceptedAmount = number_format($report->amount, 2, '.', '');
        $this->decisionReason = '';
        $this->acceptModal = true;
    }

    public function openReject(): void
    {
        $report = $this->shownOrFail();
        Gate::authorize('decide', $report);

        $this->resetValidation();
        $this->decisionReason = '';
        $this->rejectModal = true;
    }

    /**
     * @return LengthAwarePaginator<int, ExpenseReport>
     */
    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['user', 'refund', 'files'])
            ->orderBy('expense_reports.created_at', $this->statusFilter === 'submitted' ? 'asc' : 'desc')
            ->orderBy('expense_reports.id')
            ->paginate(25);
    }

    public function show(int $reportId): void
    {
        $report = ExpenseReport::findOrFail($reportId);
        Gate::authorize('view', $report);

        $this->shownId = $report->id;
        $this->readerDrawer = true;
        unset($this->shown);
    }

    /** The report open in the drawer, with what the decider needs next to it. */
    #[Computed]
    public function shown(): ?ExpenseReport
    {
        return $this->shownId === null
            ? null
            : ExpenseReport::with(['user', 'files', 'refund', 'decider', 'resumedFrom'])->find($this->shownId);
    }

    /**
     * @return array{submitted_count: int, submitted_total: float, unpaid_total: float, unpaid_count: int, paid_year_total: float, paid_year_count: int, year: int}
     */
    #[Computed]
    public function stats(): array
    {
        $year = (int) now()->year;
        $submitted = ExpenseReport::query()->where('status', ExpenseReportStatus::Submitted);
        $unpaid = ExpenseReport::query()->whereDisplayStatus(ExpenseReportDisplayStatus::Accepted);
        $paid = ExpenseReport::query()->paidInYear($year);

        return [
            'submitted_count' => (clone $submitted)->count(),
            'submitted_total' => round((int) (clone $submitted)->sum('amount') / 100, 2),
            'unpaid_count' => (clone $unpaid)->count(),
            'unpaid_total' => round((int) (clone $unpaid)->sum('accepted_amount') / 100, 2),
            'paid_year_count' => (clone $paid)->count(),
            'paid_year_total' => round((int) (clone $paid)->sum('accepted_amount') / 100, 2),
            'year' => $year,
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter', 'categoryFilter', 'dateFrom', 'dateTo', 'fiscalYear', 'unarchivedOnly', 'userId'], true)) {
            $this->resetPage();
        }

        if ($property === 'readerDrawer' && ! $this->readerDrawer) {
            $this->shownId = null;
        }
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
            'headers' => $this->headers(),
            'yearOptions' => collect(range((int) now()->year, 2024))->map(fn (int $year): array => ['id' => $year, 'name' => (string) $year])->all(),
        ];
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Expense reports'));
    }

    private function actor(): User
    {
        /** @var User */
        return Auth::user();
    }

    /**
     * The tab, the search and the drawer's filters — shared with the export,
     * which must take exactly what the treasurer sees.
     *
     * @return Builder<ExpenseReport>
     */
    private function filteredQuery(): Builder
    {
        return ExpenseReport::query()
            ->when(ExpenseReportDisplayStatus::tryFrom($this->statusFilter), fn (Builder $q, ExpenseReportDisplayStatus $status): Builder => $q->whereDisplayStatus($status))
            // Grouped: `when()` opens no parenthesis, and an `OR` left flat
            // would let the name branch escape the tab.
            ->when(filled($this->search), fn (Builder $q): Builder => $q->where(function (Builder $q): void {
                $q->where('description', 'like', "%{$this->search}%")
                    ->orWhereHas('user', fn (Builder $u): Builder => $u->where(fn (Builder $u): Builder => $u
                        ->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")));
            }))
            ->when($this->userId !== null, fn (Builder $q): Builder => $q->where('user_id', $this->userId))
            ->when($this->categoryFilter !== '', fn (Builder $q): Builder => $q->where('category', $this->categoryFilter))
            ->when($this->dateFrom !== '', fn (Builder $q): Builder => $q->whereDate('spent_on', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q): Builder => $q->whereDate('spent_on', '<=', $this->dateTo))
            ->when($this->fiscalYear !== null, fn (Builder $q): Builder => $q->paidInYear((int) $this->fiscalYear))
            ->when($this->unarchivedOnly, fn (Builder $q): Builder => $q->whereNull('archived_at'));
    }

    /**
     * @param  list<int>  $reportIds
     */
    private function queueExport(string $format, array $reportIds): void
    {
        if ($reportIds === []) {
            $this->warning(__('Nothing to export: no report matches the current filters.'));

            return;
        }

        $export = ExpenseReportExport::create([
            'requested_by' => $this->actor()->id,
            'format' => $format,
            'report_ids' => $reportIds,
            'status' => 'pending',
        ]);

        GenerateExpenseReportExport::dispatch($export->id);

        $this->success(__('The export is being prepared. The bell will ring when it is ready.'));
    }

    private function refreshLists(): void
    {
        unset($this->reports, $this->stats, $this->shown);
    }

    private function shownOrFail(): ExpenseReport
    {
        $report = $this->shown;

        abort_if($report === null, 404);

        return $report;
    }
};
