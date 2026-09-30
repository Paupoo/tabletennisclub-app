<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\DeleteSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\UnlinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Services\SupportingDocumentSuggestions;
use App\Domains\Shared\Enums\Feature;
use App\Domains\Shared\Enums\Permission;
use App\Domains\Shared\Enums\SupportingDocumentState;
use App\Domains\Shared\ValueObjects\FiscalYear;
use App\Livewire\Concerns\EditsSupportingDocument;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

/**
 * « Pièces justificatives »: the proofs of the money the website never saw —
 * invoices, tickets, subsidy letters, screenshots of bank fees.
 *
 * Read by whoever reads the bank lines, the committee and the accounts
 * auditors included; filed, corrected and linked by the treasury. Every
 * write is guarded here against the policy, since the route lets readers in.
 */
new class extends Component
{
    use EditsSupportingDocument, HasBreadcrumbs, HasFilterDrawer, Toast, WithFileUploads, WithPagination;

    /** The till a cash payment is recorded in. */
    public ?int $cashRegisterId = null;

    #[Url(as: 'category')]
    public string $categoryFilter = '';

    public bool $deleteModal = false;

    /**
     * The calendar year the chosen financial year starts in, see {@see FiscalYear::startingIn()}.
     */
    #[Url(as: 'year')]
    public ?int $fiscalYear = null;

    public bool $formDrawer = false;

    /** Free search for a bank line to link, past the suggestions. */
    public string $linkSearch = '';

    public bool $readerDrawer = false;

    #[Url]
    public string $search = '';

    #[Url(as: 'document')]
    public ?int $shownId = null;

    #[Url(as: 'state')]
    public string $stateFilter = '';

    /**
     * Cash movements of the till that may have paid the open document.
     *
     * @return Collection<int, CashRegisterEntry>
     */
    #[Computed]
    public function cashEntrySuggestions(): Collection
    {
        $document = $this->shown;

        if (! $document instanceof SupportingDocument || ! Feature::CashRegister->enabled()) {
            return new Collection;
        }

        return (new SupportingDocumentSuggestions)->cashEntriesFor($document);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    #[Computed]
    public function cashRegisterOptions(): array
    {
        return CashRegister::query()->orderBy('name')->orderBy('id')->get(['id', 'name'])
            ->map(fn (CashRegister $register): array => ['id' => $register->id, 'name' => $register->name])
            ->all();
    }

    public function clearFilters(): void
    {
        $this->reset(['stateFilter', 'categoryFilter', 'fiscalYear']);
        $this->resetPage();
    }

    public function confirmDelete(): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('delete', $document);

        try {
            (new DeleteSupportingDocument)($document);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->deleteModal = false;
        $this->readerDrawer = false;
        $this->shownId = null;
        $this->refreshLists();
        $this->success(__('Supporting document deleted.'));
    }

    public function create(): void
    {
        Gate::authorize('create', SupportingDocument::class);

        $this->resetDocumentForm();
        $this->formDrawer = true;
    }

    /**
     * @return LengthAwarePaginator<int, SupportingDocument>
     */
    #[Computed]
    public function documents(): LengthAwarePaginator
    {
        return $this->filteredQuery()
            ->with(['transactions', 'cashRegisterEntries'])
            ->orderByDesc('supporting_documents.date')
            ->orderBy('supporting_documents.id')
            ->paginate(25);
    }

    public function edit(): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('update', $document);

        $this->fillDocumentFormFrom($document);
        $this->formDrawer = true;
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    public function getFilterChips(): array
    {
        return array_values(array_filter([
            $this->stateFilter !== '' ? ['key' => 'stateFilter', 'label' => SupportingDocumentState::tryFrom($this->stateFilter)?->label() ?? $this->stateFilter] : null,
            $this->categoryFilter !== '' ? ['key' => 'categoryFilter', 'label' => SupportingDocument::categoryFromKey($this->categoryFilter)?->label() ?? $this->categoryFilter] : null,
            $this->fiscalYear !== null ? ['key' => 'fiscalYear', 'label' => __('Financial year :year', ['year' => FiscalYear::startingIn($this->fiscalYear)->label()])] : null,
        ]));
    }

    /**
     * @return array<int, array{key: string, label: string, sortable: bool}>
     */
    public function headers(): array
    {
        return [
            ['key' => 'reference', 'label' => __('Reference'), 'sortable' => false],
            ['key' => 'date', 'label' => __('Date'), 'sortable' => false],
            ['key' => 'counterparty', 'label' => __('Counterparty'), 'sortable' => false],
            ['key' => 'category', 'label' => __('Category'), 'sortable' => false],
            ['key' => 'amount', 'label' => __('Amount'), 'sortable' => false],
            ['key' => 'state', 'label' => __('Status'), 'sortable' => false],
        ];
    }

    public function linkCashEntry(int $entryId): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('linkCashRegisterEntry', $document);

        $this->link($document, CashRegisterEntry::findOrFail($entryId));
    }

    public function linkTransaction(int $transactionId): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('linkTransaction', SupportingDocument::class);

        $this->link($document, Transaction::findOrFail($transactionId));
    }

    public function mount(): void
    {
        Gate::authorize('viewAny', SupportingDocument::class);

        $this->cashRegisterId = CashRegister::query()->orderBy('name')->value('id');

        if ($this->shownId !== null) {
            $this->show($this->shownId);
        }
    }

    public function openDelete(): void
    {
        Gate::authorize('delete', $this->shownOrFail());

        $this->deleteModal = true;
    }

    /**
     * Pay the open document from the till: a movement of its amount, in the
     * chosen register, out for an expense and in for an income — linked at once.
     */
    public function payInCash(): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('linkCashRegisterEntry', $document);
        Gate::authorize(Permission::CashRegisterEntryCreate->value);

        $register = CashRegister::find($this->cashRegisterId);

        if (! $register instanceof CashRegister) {
            $this->error(__('No cash register selected.'));

            return;
        }

        $cents = (int) round($document->amount * 100);

        $entry = CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'amount' => $document->isExpense() ? -$cents : $cents,
            'reason' => $document->counterparty . ' — ' . $document->label,
            'recorded_by_id' => Auth::id(),
        ]);

        $this->link($document, $entry);
    }

    public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'filterChips' => $this->getFilterChips(),
            'headers' => $this->headers(),
            'stateOptions' => SupportingDocumentState::options(),
            'categoryOptions' => SupportingDocument::categoryOptions(),
            'yearOptions' => collect(range(FiscalYear::current()->startYear(), 2024))
                ->map(fn (int $year): array => ['id' => $year, 'name' => FiscalYear::startingIn($year)->label()])
                ->all(),
        ]);
    }

    public function save(): void
    {
        $editing = $this->editingDocument();
        Gate::authorize(...($editing instanceof SupportingDocument ? ['update', $editing] : ['create', SupportingDocument::class]));

        try {
            $document = $this->saveDocumentForm();
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->formDrawer = false;
        $this->refreshLists();
        $this->success($editing instanceof SupportingDocument
            ? __('Supporting document :reference saved.', ['reference' => $document->reference()])
            : __('Supporting document :reference filed.', ['reference' => $document->reference()]));

        $this->show($document->id);
    }

    public function show(int $documentId): void
    {
        $document = SupportingDocument::findOrFail($documentId);
        Gate::authorize('view', $document);

        $this->shownId = $document->id;
        $this->linkSearch = '';
        $this->readerDrawer = true;
        unset($this->shown, $this->transactionSuggestions, $this->transactionSearchResults, $this->cashEntrySuggestions);
    }

    /** The document open in the drawer, with its files and what it is linked to. */
    #[Computed]
    public function shown(): ?SupportingDocument
    {
        return $this->shownId === null
            ? null
            : SupportingDocument::with(['files', 'transactions.bankAccount', 'cashRegisterEntries.cashRegister', 'createdBy'])->find($this->shownId);
    }

    /**
     * @return array{debts_total: float, debts_count: int, receivables_total: float, receivables_count: int, year_count: int, year_settled: int, year: string}
     */
    #[Computed]
    public function stats(): array
    {
        $year = FiscalYear::current();
        $debts = SupportingDocument::query()->toSettle()->expenses();
        $receivables = SupportingDocument::query()->toSettle()->incomes();
        $thisYear = SupportingDocument::query()->datedIn($year);

        return [
            'debts_count' => (clone $debts)->count(),
            'debts_total' => round((int) (clone $debts)->sum('amount') / 100, 2),
            'receivables_count' => (clone $receivables)->count(),
            'receivables_total' => round((int) (clone $receivables)->sum('amount') / 100, 2),
            'year_count' => (clone $thisYear)->count(),
            'year_settled' => (clone $thisYear)->settled()->count(),
            'year' => $year->label(),
        ];
    }

    /**
     * Bank lines found by the free search.
     *
     * @return Collection<int, Transaction>
     */
    #[Computed]
    public function transactionSearchResults(): Collection
    {
        $document = $this->shown;

        if (! $document instanceof SupportingDocument) {
            return new Collection;
        }

        return (new SupportingDocumentSuggestions)->searchTransactions($this->linkSearch, $document->transactions->modelKeys());
    }

    /**
     * Bank lines not justified yet that may have paid the open document.
     *
     * @return Collection<int, Transaction>
     */
    #[Computed]
    public function transactionSuggestions(): Collection
    {
        $document = $this->shown;

        if (! $document instanceof SupportingDocument) {
            return new Collection;
        }

        return (new SupportingDocumentSuggestions)->transactionsFor($document);
    }

    public function unlinkCashEntry(int $entryId): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('linkCashRegisterEntry', $document);

        (new UnlinkSupportingDocument)($document, CashRegisterEntry::findOrFail($entryId));
        $this->afterLinking(__('Cash movement unlinked.'));
    }

    public function unlinkTransaction(int $transactionId): void
    {
        $document = $this->shownOrFail();
        Gate::authorize('linkTransaction', SupportingDocument::class);

        (new UnlinkSupportingDocument)($document, Transaction::findOrFail($transactionId));
        $this->afterLinking(__('Transaction unlinked: it is back among the lines to handle.'));
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'stateFilter', 'categoryFilter', 'fiscalYear'], true)) {
            $this->resetPage();
        }

        if ($property === 'linkSearch') {
            unset($this->transactionSearchResults);
        }

        if ($property === 'readerDrawer' && ! $this->readerDrawer) {
            $this->shownId = null;
        }
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Supporting documents'));
    }

    private function afterLinking(string $message): void
    {
        $this->refreshLists();
        $this->success($message);
    }

    /**
     * The search, then the drawer's filters — grouped, so the search's `OR`
     * never escapes them.
     *
     * @return Builder<SupportingDocument>
     */
    private function filteredQuery(): Builder
    {
        return SupportingDocument::query()
            ->when(filled($this->search), fn (Builder $q): Builder => $q->where(function (Builder $q): void {
                $q->where('counterparty', 'like', "%{$this->search}%")
                    ->orWhere('label', 'like', "%{$this->search}%");

                if (preg_match('/^P-\d{4}-0*(\d+)$/i', trim($this->search), $reference) === 1) {
                    $q->orWhere('supporting_documents.id', (int) $reference[1]);
                }
            }))
            ->when($this->stateFilter === SupportingDocumentState::ToSettle->value, fn (Builder $q): Builder => $q->toSettle())
            ->when($this->stateFilter === SupportingDocumentState::Settled->value, fn (Builder $q): Builder => $q->settled())
            ->when($this->categoryFilter !== '', fn (Builder $q): Builder => $q->inCategory($this->categoryFilter))
            ->when($this->fiscalYear !== null, fn (Builder $q): Builder => $q->datedIn(FiscalYear::startingIn((int) $this->fiscalYear)));
    }

    private function link(SupportingDocument $document, Transaction|CashRegisterEntry $movement): void
    {
        try {
            (new LinkSupportingDocument)($document, $movement);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->afterLinking($movement instanceof Transaction ? __('Transaction linked: it is justified.') : __('Cash movement linked.'));
    }

    private function refreshLists(): void
    {
        unset($this->documents, $this->stats, $this->shown, $this->transactionSuggestions, $this->transactionSearchResults, $this->cashEntrySuggestions);
    }

    private function shownOrFail(): SupportingDocument
    {
        $document = $this->shown;

        abort_if($document === null, 404);

        return $document;
    }
};
