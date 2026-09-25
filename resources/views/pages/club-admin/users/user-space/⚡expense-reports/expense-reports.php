<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\ExpenseReports\Actions\SubmitExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Actions\UpdateExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Actions\WithdrawExpenseReport;
use App\Domains\ClubAdmin\ExpenseReports\Models\ExpenseReport;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\ExpenseCategory;
use App\Domains\Shared\Enums\ExpenseReportDisplayStatus;
use App\Domains\Shared\Rules\ValidIban;
use App\Domains\Shared\Support\IbanNormalizer;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * The member's own expense reports: declare, correct, withdraw, resume.
 *
 * Reserved to adults acting for themselves — the policy decides, and a
 * guardian holding a ward's seat is authenticated as the ward, so the page
 * refuses them too.
 */
new class extends Component
{
    use HasBreadcrumbs, HasFilterDrawer, Toast, WithFileUploads, WithPagination;

    /** Highest number of proofs on one report. */
    private const int MAX_FILES = 5;

    public string $amount = '';

    public string $category = '';

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    public string $description = '';

    /** @var list<array{id: int, description: string, amount: float, spent_on: string}> */
    public array $duplicates = [];

    public ?int $editingId = null;

    public bool $formDrawer = false;

    /** @var array<int, mixed> */
    public array $newFiles = [];

    /** The reading drawer; {@see $shownId} says which report, and lives in the URL. */
    public bool $readerDrawer = false;

    public string $refundIban = '';

    /** @var list<int> */
    public array $removedFileIds = [];

    #[Url(as: 'resume')]
    public ?int $resumeId = null;

    public ?int $resumedFromId = null;

    #[Url(as: 'report')]
    public ?int $shownId = null;

    public string $spentOn = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public User $user;

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->statusFilter !== '' ? ['key' => 'statusFilter', 'label' => ExpenseReportDisplayStatus::tryFrom($this->statusFilter)?->label() ?? $this->statusFilter] : null,
            $this->categoryFilter !== '' ? ['key' => 'categoryFilter', 'label' => ExpenseCategory::tryFrom($this->categoryFilter)?->label() ?? $this->categoryFilter] : null,
        ]));
    }

    public function clearFilters(): void
    {
        $this->reset(['statusFilter', 'categoryFilter']);
        $this->resetPage();
    }

    /** The report being edited, with the proofs it already carries. */
    #[Computed]
    public function editing(): ?ExpenseReport
    {
        return $this->editingId === null ? null : ExpenseReport::with('files')->find($this->editingId);
    }

    public function mount(User $user): void
    {
        abort_unless(Auth::user()->is($user), 403);
        abort_unless(Gate::allows('create', ExpenseReport::class), 403);

        $this->user = $user;

        if ($this->resumeId !== null) {
            $this->resume($this->resumeId);
        } elseif ($this->shownId !== null) {
            $this->show($this->shownId);
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->refundIban = (string) IbanNormalizer::format($this->user->iban);
        $this->spentOn = now()->toDateString();
        $this->formDrawer = true;
    }

    public function openEdit(int $reportId): void
    {
        $report = $this->ownReport($reportId);
        Gate::authorize('update', $report);

        $this->resetForm();
        $this->readerDrawer = false;
        $this->fill([
            'editingId' => $report->id,
            'category' => $report->category->value,
            'description' => $report->description,
            'amount' => number_format($report->amount, 2, '.', ''),
            'spentOn' => $report->spent_on->toDateString(),
            'refundIban' => (string) IbanNormalizer::format($report->refund_iban),
        ]);
        $this->formDrawer = true;
    }

    /**
     * @return LengthAwarePaginator<int, ExpenseReport>
     */
    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return ExpenseReport::query()
            ->with(['refund', 'files'])
            ->where('user_id', $this->user->id)
            ->when($this->categoryFilter !== '', fn (Builder $q): Builder => $q->where('category', $this->categoryFilter))
            ->when(ExpenseReportDisplayStatus::tryFrom($this->statusFilter), fn (Builder $q, ExpenseReportDisplayStatus $status): Builder => $q->whereDisplayStatus($status))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);
    }

    /** Open the form on a new report, filled in from a rejected one. */
    public function resume(int $reportId): void
    {
        $report = $this->ownReport($reportId);
        Gate::authorize('resume', $report);

        $this->resetForm();
        $this->fill([
            'resumedFromId' => $report->id,
            'category' => $report->category->value,
            'description' => $report->description,
            'amount' => number_format($report->amount, 2, '.', ''),
            'spentOn' => $report->spent_on->toDateString(),
            'refundIban' => (string) IbanNormalizer::format($report->refund_iban),
        ]);
        $this->shownId = null;
        $this->readerDrawer = false;
        $this->formDrawer = true;
    }

    public function removeExistingFile(int $fileId): void
    {
        $this->removedFileIds[] = $fileId;
    }

    public function removeNewFile(int $index): void
    {
        unset($this->newFiles[$index]);
        $this->newFiles = array_values($this->newFiles);
    }

    /**
     * Submit, or save a correction.
     *
     * A likely duplicate stops the first attempt with a warning; the member
     * confirms and it goes through. Two full tanks on a weekend exist.
     */
    public function save(bool $confirmDuplicate = false): void
    {
        $this->amount = str_replace([',', ' ', '€'], ['.', '', ''], $this->amount);

        $this->validate();

        $editing = $this->editing;
        $amount = round((float) $this->amount, 2);
        $spentOn = Carbon::parse($this->spentOn);

        if ($editing === null) {
            Gate::authorize('create', ExpenseReport::class);
        } else {
            Gate::authorize('update', $editing);
        }

        $remaining = ($editing?->files->whereNotIn('id', $this->removedFileIds)->count() ?? 0) + count($this->newFiles);

        if ($remaining < 1) {
            $this->addError('newFiles', __('Attach at least one proof.'));

            return;
        }

        if ($remaining > self::MAX_FILES) {
            $this->addError('newFiles', __('At most :count proofs per report.', ['count' => self::MAX_FILES]));

            return;
        }

        if (! $confirmDuplicate) {
            $this->duplicates = ExpenseReport::possibleDuplicatesOf($this->user->id, $amount, $spentOn, $editing?->id)
                ->map(fn (ExpenseReport $report): array => [
                    'id' => $report->id,
                    'description' => $report->description,
                    'amount' => $report->amount,
                    'spent_on' => $report->spent_on->format('d/m/Y'),
                ])->values()->all();

            if ($this->duplicates !== []) {
                return;
            }
        }

        $category = ExpenseCategory::from($this->category);

        if ($editing === null) {
            (new SubmitExpenseReport)(
                author: $this->user,
                category: $category,
                description: trim($this->description),
                amount: $amount,
                spentOn: $spentOn,
                refundIban: $this->refundIban,
                files: $this->newFiles,
                resumedFrom: $this->resumedFromId !== null ? $this->ownReport($this->resumedFromId) : null,
            );
            $this->success(__('Expense report submitted. The treasury will get back to you.'));
        } else {
            (new UpdateExpenseReport)(
                report: $editing,
                category: $category,
                description: trim($this->description),
                amount: $amount,
                spentOn: $spentOn,
                refundIban: $this->refundIban,
                newFiles: $this->newFiles,
                removedFileIds: $this->removedFileIds,
            );
            $this->success(__('Expense report updated.'));
        }

        $this->resetForm();
        $this->resumeId = null;
        $this->formDrawer = false;
        unset($this->reports);
    }

    /** The report open in the reading drawer. */
    #[Computed]
    public function shown(): ?ExpenseReport
    {
        if ($this->shownId === null) {
            return null;
        }

        return ExpenseReport::with(['files', 'refund', 'decider'])
            ->where('user_id', $this->user->id)
            ->find($this->shownId);
    }

    public function show(int $reportId): void
    {
        $this->shownId = $this->ownReport($reportId)->id;
        $this->readerDrawer = true;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['statusFilter', 'categoryFilter'], true)) {
            $this->resetPage();
        }

        if ($property === 'readerDrawer' && ! $this->readerDrawer) {
            $this->shownId = null;
        }

        if (in_array($property, ['amount', 'spentOn'], true)) {
            $this->duplicates = [];
        }
    }

    public function with(): array
    {
        return [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
            'maxFiles' => self::MAX_FILES,
        ];
    }

    public function withdraw(int $reportId): void
    {
        $report = $this->ownReport($reportId);
        Gate::authorize('withdraw', $report);

        (new WithdrawExpenseReport)($report);

        $this->shownId = null;
        $this->readerDrawer = false;
        unset($this->reports);
        $this->success(__('Expense report withdrawn.'));
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('My expense reports'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $isNew = $this->editingId === null;

        return [
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'spentOn' => ['required', 'date', 'before_or_equal:today'],
            'refundIban' => ['required', new ValidIban],
            'newFiles' => [$isNew ? 'required' : 'nullable', 'array', 'max:' . self::MAX_FILES],
            'newFiles.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'category' => __('Nature'),
            'description' => __('Description'),
            'amount' => __('Amount'),
            'spentOn' => __('Date of the expense'),
            'refundIban' => __('Refund account (IBAN)'),
            'newFiles' => __('Proofs'),
            'newFiles.*' => __('Proof'),
        ];
    }

    private function ownReport(int $reportId): ExpenseReport
    {
        $report = ExpenseReport::findOrFail($reportId);

        abort_unless($report->user_id === $this->user->id, 403);

        return $report;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'resumedFromId', 'category', 'description', 'amount', 'spentOn', 'refundIban', 'newFiles', 'removedFileIds', 'duplicates']);
        $this->resetValidation();
        unset($this->editing);
    }
};
