<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Actions\ClubAdmin\Payments\UnlinkCashDepositAction;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterEntry;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\LinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Actions\UnlinkSupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Models\SupportingDocument;
use App\Domains\ClubAdmin\SupportingDocuments\Services\SupportingDocumentSuggestions;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\EditsSupportingDocument;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Support\Breadcrumb;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

new class extends Component
{
    use EditsSupportingDocument, HasBreadcrumbs, Toast, WithFileUploads;

    public bool $changeHolderModal = false;

    public bool $createRegisterModal = false;

    /** « Versement de/vers la banque » : links a movement of the till to its bank line. */
    public bool $depositDrawer = false;

    /** The movement of the till whose documents or bank deposit are open. */
    public ?int $documentEntryId = null;

    #[Rule('required|integer|not_in:0')]
    public int $entryAmount = 0;

    public bool $entryDocumentsDrawer = false;

    public string $entryDocumentSearch = '';

    #[Rule('nullable|string|max:500')]
    public ?string $entryNotes = null;

    #[Rule('required|string|in:tournament_payment,training_payment,manual')]
    public string $entryReason = 'manual';

    public bool $manualEntryModal = false;

    #[Rule('nullable|exists:users,id')]
    public ?int $newHolderUserId = null;

    #[Rule('nullable|exists:users,id')]
    public ?int $newRegisterHolderUserId = null;

    #[Rule('required|string|max:100')]
    public string $newRegisterName = 'Caisse principale';

    public bool $retireRegisterModal = false;

    public ?int $selectedRegisterId = null;

    /** Retired registers stay out of the way until you ask for them. */
    public bool $showRetired = false;

    #[Computed]
    public function balance(): int
    {
        return $this->register?->currentBalance() ?? 0;
    }

    public function confirmChangeHolder(): void
    {
        Gate::authorize(Permission::CashRegisterHolderChange->value);

        $this->validateOnly('newHolderUserId');

        $register = CashRegister::findOrFail($this->selectedRegisterId);
        $register->update(['held_by_user_id' => $this->newHolderUserId]);

        $this->changeHolderModal = false;
        unset($this->register);
        $this->success(__('Holder updated.'));
    }

    /**
     * File a new document for the open movement — prefilled with its date and
     * amount — and link it at once.
     */
    public function createAndLinkEntryDocument(): void
    {
        Gate::authorize('create', SupportingDocument::class);

        $entry = $this->documentEntryOrFail();

        try {
            $document = $this->saveDocumentForm();
            Gate::authorize('linkCashRegisterEntry', $document);
            (new LinkSupportingDocument)($document, $entry);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->afterEntryChange(__('Supporting document :reference filed and linked.', ['reference' => $document->reference()]));
    }

    public function createRegister(): void
    {
        $this->validateOnly('newRegisterName');
        $this->validateOnly('newRegisterHolderUserId');

        Gate::authorize(Permission::CashRegisterHolderChange->value);

        $register = CashRegister::create([
            'name' => $this->newRegisterName,
            'held_by_user_id' => $this->newRegisterHolderUserId,
        ]);

        $this->selectedRegisterId = $register->id;
        $this->reset(['newRegisterName', 'newRegisterHolderUserId', 'createRegisterModal']);
        unset($this->register, $this->registers);
        $this->success(__('Cash register created.'));
    }

    /**
     * Bank lines the open movement may have been deposited to, or withdrawn from.
     *
     * @return Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    #[Computed]
    public function depositSuggestions(): Illuminate\Database\Eloquent\Collection
    {
        $entry = $this->documentEntry();

        return $entry instanceof CashRegisterEntry
            ? (new SupportingDocumentSuggestions)->bankLinesForDeposit($entry)
            : new Illuminate\Database\Eloquent\Collection;
    }

    /**
     * The movement of the till open in a drawer, with its documents.
     */
    #[Computed]
    public function documentEntry(): ?CashRegisterEntry
    {
        return $this->documentEntryId === null
            ? null
            : CashRegisterEntry::with(['supportingDocuments.files', 'transaction'])->find($this->documentEntryId);
    }

    /**
     * Documents found by the free search. The cash register délégation only
     * sees those that justify no bank line.
     *
     * @return Illuminate\Database\Eloquent\Collection<int, SupportingDocument>
     */
    #[Computed]
    public function entryDocumentSearchResults(): Illuminate\Database\Eloquent\Collection
    {
        $entry = $this->documentEntry();

        if (! $entry instanceof CashRegisterEntry) {
            return new Illuminate\Database\Eloquent\Collection;
        }

        return (new SupportingDocumentSuggestions)->searchDocuments(
            $this->entryDocumentSearch,
            $entry->supportingDocuments->modelKeys(),
            withoutBankLines: Gate::denies('linkTransaction', SupportingDocument::class),
        );
    }

    /**
     * Documents still to settle this movement may pay.
     *
     * @return Illuminate\Database\Eloquent\Collection<int, SupportingDocument>
     */
    #[Computed]
    public function entryDocumentSuggestions(): Illuminate\Database\Eloquent\Collection
    {
        $entry = $this->documentEntry();

        return $entry instanceof CashRegisterEntry
            ? (new SupportingDocumentSuggestions)->documentsFor($entry)
            : new Illuminate\Database\Eloquent\Collection;
    }

    public function linkDeposit(int $transactionId): void
    {
        Gate::authorize('linkCashDeposit', SupportingDocument::class);

        try {
            (new LinkCashDepositAction)(Transaction::findOrFail($transactionId), $this->documentEntryOrFail());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->depositDrawer = false;
        $this->afterEntryChange(__('Linked to the bank: both movements are internal.'));
    }

    public function linkEntryDocument(int $documentId): void
    {
        $document = SupportingDocument::findOrFail($documentId);
        Gate::authorize('linkCashRegisterEntry', $document);

        try {
            (new LinkSupportingDocument)($document, $this->documentEntryOrFail());
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->afterEntryChange(__('Supporting document linked.'));
    }

    public function mount(): void
    {
        $register = CashRegister::first();
        $this->selectedRegisterId = $register?->id;
    }

    public function openChangeHolder(): void
    {
        Gate::authorize(Permission::CashRegisterHolderChange->value);

        $this->newHolderUserId = $this->register?->held_by_user_id;
        $this->changeHolderModal = true;
    }

    public function openDeposit(int $entryId): void
    {
        Gate::authorize('linkCashDeposit', SupportingDocument::class);

        $this->documentEntryId = CashRegisterEntry::findOrFail($entryId)->id;
        $this->depositDrawer = true;
        unset($this->documentEntry, $this->depositSuggestions);
    }

    /**
     * The documents of a movement of the till: to file or link one, or to
     * read what justifies it.
     */
    public function openEntryDocuments(int $entryId): void
    {
        Gate::authorize('viewAny', SupportingDocument::class);

        $entry = CashRegisterEntry::findOrFail($entryId);

        $this->documentEntryId = $entry->id;
        $this->entryDocumentSearch = '';
        $this->prefillDocumentFormFrom($entry);
        $this->entryDocumentsDrawer = true;
        unset($this->documentEntry, $this->entryDocumentSuggestions, $this->entryDocumentSearchResults);
    }

    public function openManualEntry(): void
    {
        Gate::authorize(Permission::CashRegisterEntryCreate->value);

        $this->reset(['entryAmount', 'entryReason', 'entryNotes']);
        $this->entryReason = 'manual';
        $this->manualEntryModal = true;
    }

    public function openRetireRegister(): void
    {
        Gate::authorize(Permission::CashRegisterManage->value);

        $this->retireRegisterModal = true;
    }

    public function reasonOptions(): array
    {
        return [
            ['id' => 'manual',             'name' => __('Manual')],
            ['id' => 'tournament_payment', 'name' => __('Tournament payment')],
            ['id' => 'training_payment',   'name' => __('Training payment')],
        ];
    }

    #[Computed]
    public function register(): ?CashRegister
    {
        if (! $this->selectedRegisterId) {
            return null;
        }

        return CashRegister::with(['entries.recordedBy', 'entries.supportingDocuments', 'entries.transaction', 'heldBy'])->find($this->selectedRegisterId);
    }

    #[Computed]
    public function registers(): Illuminate\Database\Eloquent\Collection
    {
        return CashRegister::query()
            ->when($this->showRetired, fn ($query) => $query->withTrashed())
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'reasonOptions' => $this->reasonOptions(),
            'users' => $this->users,
        ]);
    }

    public function restoreRegister(int $registerId): void
    {
        Gate::authorize(Permission::CashRegisterManage->value);

        $register = CashRegister::onlyTrashed()->findOrFail($registerId);
        $register->restore();

        unset($this->register, $this->registers);

        $this->success(__('The cash register :name is back in service.', ['name' => $register->name]));
    }

    /**
     * Take a register out of service without erasing its books.
     *
     * `cash_register_entries` cascades on delete, so a real DELETE would take
     * the whole ledger with it. Retiring keeps every movement and lets the
     * register come back.
     */
    public function retireRegister(): void
    {
        Gate::authorize(Permission::CashRegisterManage->value);

        $register = CashRegister::findOrFail($this->selectedRegisterId);
        $register->delete();

        $this->reset(['retireRegisterModal']);
        $this->selectedRegisterId = CashRegister::value('id');
        unset($this->register, $this->registers);

        $this->success(__('The cash register :name has been retired.', ['name' => $register->name]));
    }

    public function saveManualEntry(): void
    {
        Gate::authorize(Permission::CashRegisterEntryCreate->value);

        $this->validate([
            'entryAmount' => 'required|integer|not_in:0',
            'entryReason' => 'required|string',
            'entryNotes' => 'nullable|string|max:500',
        ]);

        $register = CashRegister::find($this->selectedRegisterId);
        if (! $register) {
            $this->error(__('No cash register selected.'));

            return;
        }

        CashRegisterEntry::create([
            'cash_register_id' => $register->id,
            'amount' => $this->entryAmount * 100,
            'reason' => $this->entryReason,
            'notes' => $this->entryNotes,
            'recorded_by_id' => Auth::id(),
        ]);

        unset($this->register);
        $this->reset(['entryAmount', 'entryReason', 'entryNotes', 'manualEntryModal']);
        $this->success(__('Entry recorded.'));
    }

    public function unlinkDeposit(int $entryId): void
    {
        Gate::authorize('linkCashDeposit', SupportingDocument::class);

        (new UnlinkCashDepositAction)(CashRegisterEntry::findOrFail($entryId));

        $this->afterEntryChange(__('Unlinked from the bank: the bank line is back among the lines to handle.'));
    }

    public function unlinkEntryDocument(int $documentId): void
    {
        $document = SupportingDocument::findOrFail($documentId);
        Gate::authorize('linkCashRegisterEntry', $document);

        (new UnlinkSupportingDocument)($document, $this->documentEntryOrFail());

        $this->afterEntryChange(__('Document unlinked.'));
    }

    public function updatedEntryDocumentSearch(): void
    {
        unset($this->entryDocumentSearchResults);
    }

    /**
     * Everyone a register may be handed to, filtered in the browser.
     *
     * Active members only. The current holder is already shown above the
     * button that opens this picker, so there is nothing to lose by leaving a
     * departed member out of the list they can no longer be chosen from.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    #[Computed]
    public function users(): Collection
    {
        return User::active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->first_name . ' ' . $u->last_name]);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Treasury — Cash Register'));
    }

    private function afterEntryChange(string $message): void
    {
        unset($this->register, $this->documentEntry, $this->entryDocumentSuggestions, $this->entryDocumentSearchResults, $this->depositSuggestions);

        $entry = $this->documentEntry();

        if ($entry instanceof CashRegisterEntry) {
            $this->prefillDocumentFormFrom($entry);
        }

        $this->success($message);
    }

    private function documentEntryOrFail(): CashRegisterEntry
    {
        $entry = $this->documentEntry();

        abort_if($entry === null, 404);

        return $entry;
    }
};
