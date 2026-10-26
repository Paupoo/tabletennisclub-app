<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\CountCashRegisterAction;
use App\Actions\ClubAdmin\Payments\LinkCashDepositAction;
use App\Actions\ClubAdmin\Payments\UnlinkCashDepositAction;
use App\Domains\ClubAdmin\Payment\Models\CashRegister;
use App\Domains\ClubAdmin\Payment\Models\CashRegisterCount;
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

    /** What the treasurer found in the drawer, in euros as typed. */
    public string $countedAmount = '';

    public bool $countModal = false;

    public ?string $countNotes = null;

    /** « Versement de/vers la banque » : links a movement of the till to its bank line. */
    public bool $depositDrawer = false;

    /** The movement of the till whose documents or bank deposit are open. */
    public ?int $documentEntryId = null;

    /**
     * In euros as typed — a comma or a dot, down to the cent.
     */
    public string $entryAmount = '';

    public bool $entryDocumentsDrawer = false;

    public string $entryDocumentSearch = '';

    #[Rule('nullable|string|max:500')]
    public ?string $entryNotes = null;

    #[Rule('required|string|in:tournament_payment,training_payment,manual')]
    public string $entryReason = 'manual';

    public bool $manualEntryModal = false;

    #[Rule('nullable|exists:users,id')]
    public ?int $newHolderUserId = null;

    #[Computed]
    public function balance(): int
    {
        return $this->register?->currentBalance() ?? 0;
    }

    public function confirmChangeHolder(): void
    {
        Gate::authorize(Permission::CashRegisterHolderChange->value);

        $this->validateOnly('newHolderUserId');

        CashRegister::club()->update(['held_by_user_id' => $this->newHolderUserId]);

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

    /**
     * The till's latest count, the one the next difference starts from.
     */
    #[Computed]
    public function lastCount(): ?CashRegisterCount
    {
        return CashRegisterCount::query()->with('countedBy')->latest('created_at')->latest('id')->first();
    }

    public function openChangeHolder(): void
    {
        Gate::authorize(Permission::CashRegisterHolderChange->value);

        $this->newHolderUserId = $this->register?->held_by_user_id;
        $this->changeHolderModal = true;
    }

    public function openCount(): void
    {
        Gate::authorize(Permission::CashRegisterEntryCreate->value);

        $this->reset(['countedAmount', 'countNotes']);
        $this->resetErrorBag();
        $this->countModal = true;
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

    public function reasonOptions(): array
    {
        return [
            ['id' => 'manual',             'name' => __('Manual')],
            ['id' => 'tournament_payment', 'name' => __('Tournament payment')],
            ['id' => 'training_payment',   'name' => __('Training payment')],
        ];
    }

    /**
     * The club's till, once something has opened it — reading the screen
     * opens nothing.
     */
    #[Computed]
    public function register(): ?CashRegister
    {
        return CashRegister::with(['entries.recordedBy', 'entries.supportingDocuments', 'entries.transaction', 'heldBy'])
            ->orderBy('id')
            ->first();
    }

    public function render(): View
    {
        return $this->view([
            'breadcrumbs' => $this->getBreadcrumbs(),
            'reasonOptions' => $this->reasonOptions(),
            'users' => $this->users,
        ]);
    }

    /**
     * Record what the drawer held: the app files the difference itself.
     */
    public function saveCount(): void
    {
        Gate::authorize(Permission::CashRegisterEntryCreate->value);

        $this->countedAmount = str_replace(',', '.', trim($this->countedAmount));

        $this->validate([
            'countedAmount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'countNotes' => ['nullable', 'string', 'max:500'],
        ]);

        $count = (new CountCashRegisterAction)(
            CashRegister::club(),
            (int) round((float) $this->countedAmount * 100),
            Auth::user(),
            $this->countNotes ?: null,
        );

        unset($this->register, $this->balance, $this->lastCount);
        $this->reset(['countedAmount', 'countNotes', 'countModal']);

        $count->difference() === 0
            ? $this->success(__('The till is right.'))
            : $this->warning(__('A difference of :amount € was recorded and filed.', ['amount' => number_format($count->difference() / 100, 2, ',', ' ')]));
    }

    public function saveManualEntry(): void
    {
        Gate::authorize(Permission::CashRegisterEntryCreate->value);

        $this->entryAmount = str_replace(',', '.', trim($this->entryAmount));

        $this->validate([
            'entryAmount' => ['required', 'numeric', 'decimal:0,2', 'not_in:0'],
            'entryReason' => 'required|string',
            'entryNotes' => 'nullable|string|max:500',
        ]);

        $cents = (int) round((float) $this->entryAmount * 100);

        if ($cents === 0) {
            $this->addError('entryAmount', __('Enter an amount other than zero.'));

            return;
        }

        CashRegisterEntry::create([
            'cash_register_id' => CashRegister::club()->id,
            'amount' => $cents,
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
