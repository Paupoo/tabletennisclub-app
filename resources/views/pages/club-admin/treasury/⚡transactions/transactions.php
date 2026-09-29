<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\ImportBankStatementAction;
use App\Actions\ClubAdmin\Payments\RegisterBankAccountAction;
use App\Actions\ClubAdmin\Payments\ResolveSuspectedDuplicateAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\BankAccount;
use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\PaymentCredit;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Payment\Services\TransactionMatcher;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\BankAccountType;
use App\Domains\Shared\Enums\Permission;
use App\Exceptions\UnknownBankAccount;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithFileUploads, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    public string $accountFilter = '';

    public bool $allocationModal = false;

    /** @var array<string, float|string> payment_id => montant en euros */
    public array $allocations = [];

    public string $allocationSearch = '';

    public ?int $allocationTransactionId = null;

    public string $amountDirection = '';

    public bool $confirmDeleteModal = false;

    // Drawer filters
    public string $dateFrom = '';

    public string $dateTo = '';

    public mixed $importFile = null;

    public bool $importModal = false;

    public string $newAccountName = '';

    public string $newAccountType = 'current';

    public string $reconciledFilter = '';

    public int $reconciledInSelection = 0;

    public string $residueReason = '';

    public string $search = '';

    public array $sortBy = ['column' => 'date', 'direction' => 'desc'];

    /**
     * L'IBAN d'un relevé que le club n'a pas encore enregistré : l'import
     * s'arrête et demande au trésorier ce qu'est ce compte.
     */
    public ?string $unknownAccountIban = null;

    /**
     * Les paiements que cette ligne de relevé pourrait solder, les plus
     * probables d'abord.
     *
     * `TransactionMatcher` note d'habitude des transactions pour un paiement ;
     * on l'interroge ici dans l'autre sens, paiement par paiement. C'est le
     * même barème — celui qui sait déjà remonter l'IBAN et le nom de chaque
     * tuteur, donc reconnaître les deux enfants derrière le virement d'un
     * parent.
     *
     * @return Collection<int, Payment>
     */
    #[Computed]
    public function allocationCandidates(): Collection
    {
        $transaction = $this->allocationTransaction();

        // Une ligne d'épargne ou un mouvement interne n'est le paiement de
        // personne : le rapprochement ne regarde que les comptes courants.
        if (! $transaction instanceof Transaction || ! Transaction::reconcilable()->whereKey($transaction->id)->exists()) {
            return collect();
        }

        // Un débit rembourse : ses candidats sont les remboursements engagés.
        //
        // Les trois payables qui portent un membre sont chargés, pas seulement
        // l'affiliation : `TransactionMatcher::payer()` lit `$payable->user`
        // pour chacun d'eux, et dix-neuf des créances ouvertes de la base de
        // démonstration sont des inscriptions à un tournoi. N'en charger qu'un
        // fait tomber l'écran en LazyLoadingViolation.
        $payments = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith([
            Subscription::class => ['user.guardians', 'season'],
            TournamentRegistration::class => ['user.guardians', 'tournament'],
            MeetingUser::class => ['user.guardians', 'meeting'],
        ])])
            ->where('status', (float) $transaction->amount < 0 ? 'to_refund' : 'pending')
            ->get()
            ->filter(fn (Payment $payment): bool => $this->outstandingOf($payment) > 0.0);

        // La recherche, pour aller chercher quelqu'un que le barème ne propose
        // pas en tête : sur un club entier, trente candidats ne se lisent pas.
        if (trim($this->allocationSearch) !== '') {
            $needle = mb_strtolower(trim($this->allocationSearch));

            $payments = $payments->filter(fn (Payment $payment): bool => str_contains(
                mb_strtolower($this->payerNameOf($payment) . ' ' . $payment->reference),
                $needle,
            ));
        }

        $matcher = new TransactionMatcher;

        // Le verdict est attaché à la ligne, pas jeté : c'est lui qui dit au
        // trésorier *pourquoi* un candidat est proposé, et sans cette raison
        // une liste triée ressemble à une liste au hasard.
        return $payments
            ->map(function (Payment $payment) use ($matcher, $transaction): Payment {
                $payment->match = $matcher->score($payment, $transaction, false);

                return $payment;
            })
            ->sortByDesc(fn (Payment $payment): int => $payment->match->strength->rank())
            ->values();
    }

    /**
     * Le paiement courant du tiroir, s'il y en a un.
     */
    #[Computed]
    public function allocationTransaction(): ?Transaction
    {
        return $this->allocationTransactionId
            ? Transaction::find($this->allocationTransactionId)
            : null;
    }

    /**
     * Les comptes du club, pour le filtre et pour nommer le compte d'une ligne.
     *
     * @return Collection<int, BankAccount>
     */
    #[Computed]
    public function bankAccounts(): Collection
    {
        return BankAccount::orderBy('type')->orderBy('name')->orderBy('id')->get();
    }

    public function bulkDelete(): void
    {
        Gate::authorize(Permission::TransactionsDelete->value);

        $ids = array_map(intval(...), $this->selected);

        if ($this->selectingAllResults) {
            $ids = $this->allMatchingTransactionIds();
        }

        Transaction::whereIn('id', $ids)->get()->each(fn (Transaction $transaction) => $transaction->delete());

        $this->confirmDeleteModal = false;
        $this->reconciledInSelection = 0;
        $this->clearSelection();
        $this->success(__(':count transaction(s) deleted.', ['count' => count($ids)]));
    }

    public function clearFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo', 'reconciledFilter', 'amountDirection', 'accountFilter']);
        $this->resetPage();
    }

    public function confirmAllocation(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $transaction = $this->allocationTransaction();

        if (! $transaction instanceof Transaction) {
            return;
        }

        $wanted = collect($this->allocations)
            ->map(fn (float|string $amount): float => round((float) $amount, 2))
            ->filter(fn (float $amount): bool => $amount > 0.0)
            ->mapWithKeys(fn (float $amount, int|string $paymentId): array => [(int) $paymentId => $amount])
            ->all();

        if ($wanted === []) {
            $this->error(__('Nothing to allocate.'));

            return;
        }

        try {
            (new AllocateTransactionAction)($transaction, $wanted);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->closeAllocation();
        $this->success(__(':count allocation(s) recorded.', ['count' => count($wanted)]));
    }

    /**
     * Écarte une ligne que l'import avait mise de côté : c'est bien le même virement.
     */
    public function dismissSuspectedDuplicate(int $bankImportId, int $line): void
    {
        $this->resolveSuspectedDuplicate($bankImportId, $line, keep: false);
    }

    // ==================== HasFilterDrawer ====================

    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->dateFrom) {
            $chips[] = ['key' => 'dateFrom', 'label' => __('From: :date', ['date' => $this->dateFrom])];
        }

        if ($this->dateTo) {
            $chips[] = ['key' => 'dateTo', 'label' => __('To: :date', ['date' => $this->dateTo])];
        }

        if ($this->reconciledFilter) {
            $label = match ($this->reconciledFilter) {
                'reconciled' => __('Settled'),
                'partial' => __('Partly allocated'),
                'internal' => __('Internal'),
                'justified' => __('Justified'),
                default => __('Unreconciled'),
            };
            $chips[] = ['key' => 'reconciledFilter', 'label' => $label];
        }

        if ($this->amountDirection) {
            $label = $this->amountDirection === 'credit' ? __('Credit') : __('Debit');
            $chips[] = ['key' => 'amountDirection', 'label' => $label];
        }

        if ($this->accountFilter) {
            $chips[] = ['key' => 'accountFilter', 'label' => $this->bankAccounts()->firstWhere('id', (int) $this->accountFilter)->name ?? '—'];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->transactions()->total();
    }

    public function headers(): array
    {
        return [
            ['key' => 'date',                 'label' => __('Date'),        'sortable' => true],
            ['key' => 'counterparty_name',    'label' => __('Counterparty'), 'sortable' => true],
            ['key' => 'structured_reference', 'label' => __('Reference'),   'sortable' => false],
            ['key' => 'amount',               'label' => __('Amount'),      'sortable' => true],
            ['key' => 'status',               'label' => __('Status'),      'sortable' => false],
            ['key' => 'allocate',             'label' => '',                'sortable' => false],
        ];
    }

    // ==================== Actions ====================

    /**
     * Garde une ligne que l'import avait mise de côté : c'est un autre virement.
     */
    public function keepSuspectedDuplicate(int $bankImportId, int $line): void
    {
        $this->resolveSuspectedDuplicate($bankImportId, $line, keep: true);
    }

    /**
     * Ouvre directement le tiroir quand on arrive avec `?allocate=`.
     *
     * Le trésorier vient de rapprocher depuis l'écran Paiements et suit le lien
     * du bandeau : il doit atterrir sur le geste, pas sur une liste où
     * retrouver sa ligne.
     */
    public function mount(): void
    {
        $id = request()->integer('allocate');

        if ($id > 0 && Transaction::whereKey($id)->exists()) {
            $this->openAllocation($id);
        }
    }

    public function openAllocation(int $transactionId): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $this->allocationTransactionId = $transactionId;
        $this->allocations = [];
        $this->allocationSearch = '';
        $this->residueReason = '';
        $this->allocationModal = true;

        unset($this->allocationTransaction, $this->allocationCandidates, $this->servedCredits, $this->refundableClaim);
    }

    // ==================== Bulk actions ====================

    public function openConfirmDeleteModal(): void
    {
        Gate::authorize(Permission::TransactionsDelete->value);

        $ids = array_map(intval(...), $this->selected);

        // Tout ce qui n'est pas vierge : une ligne affectée en partie porte
        // elle aussi des crédits qui disparaîtraient avec elle.
        $this->reconciledInSelection = Transaction::whereIn('id', $ids)
            ->whereNot(fn (Builder $q): Builder => $q->unallocated())
            ->count();
        $this->confirmDeleteModal = true;
    }

    public function processImport(): void
    {
        Gate::authorize(Permission::TransactionsImport->value);

        $this->validate(['importFile' => 'required|file|mimes:ods,xlsx,xls,csv,txt']);

        try {
            $import = (new ImportBankStatementAction)($this->importFile->getRealPath());
        } catch (UnknownBankAccount $e) {
            // Rien n'est importé : le modal reste ouvert, fichier compris, et
            // demande ce qu'est ce compte avant de recommencer.
            $this->unknownAccountIban = $e->ibans[0];
            $this->newAccountName = '';
            $this->newAccountType = BankAccountType::Current->value;

            return;
        } catch (Throwable $e) {
            $this->error(__('Error reading file: :message', ['message' => $e->getMessage()]), timeout: 10000);

            return;
        }

        $this->importModal = false;
        $this->importFile = null;
        $this->unknownAccountIban = null;

        $suspectedCount = count($import->suspectedDuplicates());

        if ($import->new_count + $import->duplicate_count + $import->error_count + $suspectedCount === 0) {
            $this->warning(__('This statement carries no movement.'));

            return;
        }

        $message = __(':count new transaction(s) imported.', ['count' => $import->new_count]);

        if ($import->duplicate_count > 0) {
            $message .= ' ' . __(':count duplicate(s) skipped.', ['count' => $import->duplicate_count]);
        }

        if ($suspectedCount > 0) {
            $message .= ' ' . __(':count probable duplicate(s) to check — see import history.', ['count' => $suspectedCount]);
        }

        if ($import->error_count > 0) {
            $message .= ' ' . __(':count error(s) — see import history.', ['count' => $import->error_count]);
        }

        if ($import->error_count > 0 || $suspectedCount > 0) {
            $this->warning($message, timeout: 10000);
        } else {
            $this->success($message);
        }
    }

    /**
     * La créance qui recevrait le reliquat pour qu'il soit rendu, s'il y en a une.
     *
     * Un virement qui a déjà payé quelqu'un a un payeur connu : son surplus est
     * un trop-perçu, qui se rend au compte d'où il vient. La dernière créance
     * servie le porte — le choix est sans effet comptable, l'argent retourne au
     * même compte. Un virement qui n'a encore payé personne n'en a pas : on ne
     * devine pas à qui rendre.
     *
     * Seule une affiliation se rembourse ; ailleurs, le trop-perçu n'aurait
     * aucune porte de sortie.
     */
    #[Computed]
    public function refundableClaim(): ?Payment
    {
        $transaction = $this->allocationTransaction();

        if (! $transaction instanceof Transaction || (float) $transaction->amount <= 0.0 || $transaction->isSettled()) {
            return null;
        }

        return $this->servedCredits()
            ->map(fn (PaymentCredit $credit): ?Payment => $credit->payment)
            ->last(fn (?Payment $payment): bool => $payment?->payable instanceof Subscription);
    }

    /**
     * Enregistre le compte que l'import vient de rencontrer, puis reprend
     * l'import du même fichier.
     */
    public function registerAccountAndImport(): void
    {
        Gate::authorize(Permission::TransactionsImport->value);

        if ($this->unknownAccountIban === null) {
            return;
        }

        $this->validate([
            'newAccountName' => 'required|string|max:255',
            'newAccountType' => ['required', Rule::enum(BankAccountType::class)],
        ]);

        (new RegisterBankAccountAction)($this->unknownAccountIban, $this->newAccountName, BankAccountType::from($this->newAccountType));

        $this->unknownAccountIban = null;
        unset($this->bankAccounts);

        if ($this->importFile !== null) {
            $this->processImport();
        }
    }

    /**
     * Le reste à placer sur la ligne courante, en euros.
     */
    #[Computed]
    public function remainingToAllocate(): float
    {
        $transaction = $this->allocationTransaction();

        if (! $transaction instanceof Transaction) {
            return 0.0;
        }

        $claimed = collect($this->allocations)->sum(fn (float|string $amount): float => round((float) $amount, 2));

        return round(abs($transaction->residue()) - $claimed, 2);
    }

    public function render(): View
    {
        $recentImports = BankImport::with('user')->latest()->limit(10)->get();

        return $this->view([
            'headers' => $this->headers(),
            'transactions' => $this->transactions(),
            'filterChips' => $this->getFilterChips(),
            'reconciledOptions' => [
                ['id' => 'unreconciled', 'name' => __('Unreconciled')],
                ['id' => 'partial',      'name' => __('Partly allocated')],
                ['id' => 'reconciled',   'name' => __('Settled')],
                ['id' => 'justified',    'name' => __('Justified by a document')],
                ['id' => 'internal',     'name' => __('Internal transfer')],
            ],
            'amountDirectionOptions' => [
                ['id' => 'credit', 'name' => __('Credit (incoming)')],
                ['id' => 'debit',  'name' => __('Debit (outgoing)')],
            ],
            'accountOptions' => $this->bankAccounts->map(fn (BankAccount $account): array => ['id' => (string) $account->id, 'name' => $account->label()])->all(),
            'accountTypeOptions' => BankAccountType::options(),
            'showAccount' => $this->bankAccounts->count() > 1,
            'recentImports' => $recentImports,
            // Les transactions que chaque doublon probable recoupe, pour que le
            // trésorier compare les deux lignes avant de trancher.
            'lookAlikes' => Transaction::whereIn(
                'id',
                $recentImports->flatMap(fn (BankImport $import): array => array_column($import->suspectedDuplicates(), 'transaction_id')),
            )->get()->keyBy('id'),
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    /**
     * Rend le reliquat au payeur : il devient un trop-perçu sur la créance que
     * le virement a payée, et le trésorier atterrit sur la demande de
     * remboursement, préremplie.
     */
    public function returnResidue(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);
        Gate::authorize(Permission::PaymentsRefund->value);

        $transaction = $this->allocationTransaction();
        $claim = $this->refundableClaim();

        if (! $transaction instanceof Transaction || ! $claim instanceof Payment) {
            return;
        }

        try {
            (new AllocateTransactionAction)($transaction, [$claim->id => round(abs($transaction->residue()), 2)]);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->closeAllocation();
        $this->redirectRoute('admin.treasury.payments', ['refund' => $claim->id], navigate: true);
    }

    /**
     * Ce que la ligne courante a déjà payé, dans l'ordre où elle l'a payé.
     *
     * Sans cette liste, « Affecté 20,00 € » ne dit pas à qui, et le tiroir
     * concluait que le virement ne désignait personne alors qu'il venait de
     * solder une affiliation.
     *
     * @return Collection<int, PaymentCredit>
     */
    #[Computed]
    public function servedCredits(): Collection
    {
        $transaction = $this->allocationTransaction();

        if (! $transaction instanceof Transaction) {
            return collect();
        }

        return $transaction->credits()
            ->with(['payment.payable' => fn (MorphTo $m) => $m->morphWith([
                Subscription::class => ['user', 'season'],
                TournamentRegistration::class => ['user', 'tournament'],
                MeetingUser::class => ['user', 'meeting'],
            ])])
            ->orderBy('id')
            ->get();
    }

    public function settleResidue(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $transaction = $this->allocationTransaction();

        if (! $transaction instanceof Transaction) {
            return;
        }

        try {
            (new SettleTransactionResidueAction)($transaction, $this->residueReason);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->closeAllocation();
        $this->success(__('Residue written off.'));
    }

    // ==================== Data ====================

    #[Computed]
    public function stats(): array
    {
        return [
            'total' => Transaction::count(),
            'reconciled' => Transaction::settled()->count(),
            'partial' => Transaction::partiallyAllocated()->count(),
            'unreconciled' => Transaction::unallocated()->count(),
        ];
    }

    /**
     * Pré-remplit ce qu'on propose d'affecter à cette ligne.
     *
     * Un clic plutôt qu'un calcul : le trésorier a sous les yeux ce qui reste
     * sur la transaction et ce que le paiement réclame, et il n'a aucune raison
     * de faire la soustraction lui-même.
     */
    public function suggestAllocation(int $paymentId): void
    {
        $payment = Payment::find($paymentId);

        if (! $payment instanceof Payment) {
            return;
        }

        $this->allocations[(string) $paymentId] = $this->suggestedFor($payment, $this->remainingToAllocate());

        unset($this->remainingToAllocate);
    }

    public function transactions(): LengthAwarePaginator
    {
        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        return $this->applyFilters(Transaction::with(['credits', 'bankAccount', 'supportingDocuments']))
            ->orderBy($col, $dir)
            ->orderBy('transactions.id', $dir)
            ->paginate(25);
    }

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAmountDirection(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedReconciledFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Treasury — Transactions'));
    }

    // ==================== HasBulkActions ====================

    protected function getPageIds(): array
    {
        return $this->transactions()->pluck('id')->map(fn ($id): string => (string) $id)->toArray();
    }

    private function allMatchingTransactionIds(): array
    {
        return $this->applyFilters(Transaction::query())
            ->pluck('id')
            ->toArray();
    }

    /**
     * Les filtres de l'écran.
     *
     * Partagée par la liste et par « sélectionner tous les résultats » : les deux
     * doivent désigner le même ensemble, et deux copies l'ont déjà démenti.
     *
     * La recherche est enfermée dans son propre groupe parce que `when()` n'ouvre
     * aucune parenthèse et que `AND` lie plus fort que `OR` : à plat, une ligne
     * dont le tiers correspond échappe aux filtres de date, de rapprochement et
     * de sens du montant.
     *
     * @param  Builder<Transaction>  $q
     * @return Builder<Transaction>
     */
    private function applyFilters(Builder $q): Builder
    {
        return $q
            ->when($this->search, fn (Builder $q): Builder => $q->where(function (Builder $q): void {
                $q->where('counterparty_name', 'like', "%{$this->search}%")
                    ->orWhere('structured_reference', 'like', "%{$this->search}%")
                    ->orWhere('free_reference', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
            }))
            ->when($this->dateFrom, fn (Builder $q): Builder => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn (Builder $q): Builder => $q->whereDate('date', '<=', $this->dateTo))
            ->when($this->reconciledFilter === 'reconciled', fn (Builder $q): Builder => $q->settled())
            ->when($this->reconciledFilter === 'partial', fn (Builder $q): Builder => $q->partiallyAllocated())
            ->when($this->reconciledFilter === 'unreconciled', fn (Builder $q): Builder => $q->unallocated())
            ->when($this->reconciledFilter === 'internal', fn (Builder $q): Builder => $q->internal())
            ->when($this->reconciledFilter === 'justified', fn (Builder $q): Builder => $q->justified())
            ->when($this->accountFilter, fn (Builder $q): Builder => $q->where('bank_account_id', (int) $this->accountFilter))
            ->when($this->amountDirection === 'credit', fn (Builder $q): Builder => $q->where('amount', '>', 0))
            ->when($this->amountDirection === 'debit', fn (Builder $q): Builder => $q->where('amount', '<', 0));
    }

    private function closeAllocation(): void
    {
        $this->reset(['allocationModal', 'allocationTransactionId', 'allocations', 'allocationSearch', 'residueReason']);

        unset($this->allocationTransaction, $this->allocationCandidates, $this->remainingToAllocate, $this->stats);
    }

    private function outstandingOf(Payment $payment): float
    {
        // Sur un remboursement, ce qui reste à faire est ce qui n'est pas
        // encore **sorti**. `amount_paid` ne le dit pas de façon fiable : deux
        // formes de `to_refund` coexistent, et celle héritée d'un paiement
        // encaissé puis basculé garde l'encaissement d'origine dans cette
        // colonne. Les crédits adossés à une transaction de débit, eux, ne
        // décrivent que des sorties.
        if ($payment->status === 'to_refund') {
            $paidOut = abs((float) $payment->credits()
                ->whereHas('transaction', fn (Builder $q): Builder => $q->where('amount', '<', 0))
                ->sum('amount')) / 100;

            return max(0.0, round((float) $payment->amount_due - $paidOut, 2));
        }

        return max(0.0, round((float) $payment->amount_due - (float) $payment->amount_paid, 2));
    }

    private function payerNameOf(Payment $payment): string
    {
        $payable = $payment->payable;

        return $payable instanceof DescribesPayment ? $payable->getPayerName() : '—';
    }

    private function resolveSuspectedDuplicate(int $bankImportId, int $line, bool $keep): void
    {
        Gate::authorize(Permission::TransactionsImport->value);

        try {
            (new ResolveSuspectedDuplicateAction)(BankImport::findOrFail($bankImportId), $line, $keep);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->success($keep ? __('Transaction imported.') : __('Duplicate discarded.'));
    }

    /**
     * Ce que cette ligne de paiement réclame encore, en euros.
     *
     * Sur un remboursement, `amount_due` porte l'engagement et `amount_paid` ce
     * qui est déjà sorti : la soustraction dit la même chose dans les deux sens.
     */
    /**
     * Ce qu'on propose d'affecter à cette ligne : le plus petit des deux
     * restes. Le trésorier n'a plus qu'à confirmer au lieu de calculer.
     */
    private function suggestedFor(Payment $payment, float $remaining): float
    {
        return round(min($this->outstandingOf($payment), max(0.0, $remaining)), 2);
    }
};
