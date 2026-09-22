<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Payments\SettleTransactionResidueAction;
use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Services\TransactionMatcher;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithFileUploads, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    /** @var array<string, float|string> payment_id => montant en euros */
    public array $allocations = [];

    public bool $allocationModal = false;

    public ?int $allocationTransactionId = null;

    public string $residueReason = '';

    public string $amountDirection = '';

    public bool $confirmDeleteModal = false;

    // Drawer filters
    public string $dateFrom = '';

    public string $dateTo = '';

    public mixed $importFile = null;

    public bool $importModal = false;

    public string $reconciledFilter = '';

    public int $reconciledInSelection = 0;

    public string $search = '';

    public array $sortBy = ['column' => 'date', 'direction' => 'desc'];

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

        if (! $transaction instanceof Transaction) {
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

        $matcher = new TransactionMatcher;

        return $payments
            ->sortByDesc(fn (Payment $payment): int => $matcher->score($payment, $transaction, false)->strength->rank())
            ->values();
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

    public function openAllocation(int $transactionId): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $this->allocationTransactionId = $transactionId;
        $this->allocations = [];
        $this->residueReason = '';
        $this->allocationModal = true;

        unset($this->allocationTransaction, $this->allocationCandidates);
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
        $this->reset(['dateFrom', 'dateTo', 'reconciledFilter', 'amountDirection']);
        $this->resetPage();
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
                default => __('Unreconciled'),
            };
            $chips[] = ['key' => 'reconciledFilter', 'label' => $label];
        }

        if ($this->amountDirection) {
            $label = $this->amountDirection === 'credit' ? __('Credit') : __('Debit');
            $chips[] = ['key' => 'amountDirection', 'label' => $label];
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

    // ==================== Actions ====================

    public function processImport(): void
    {
        Gate::authorize(Permission::TransactionsImport->value);

        $this->validate(['importFile' => 'required|file|mimes:ods,xlsx,xls,csv,txt']);

        $path = $this->importFile->getRealPath();

        try {
            $reader = IOFactory::createReaderForFile($path);
            if ($reader instanceof Csv) {
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
            }
            $spreadsheet = $reader->load($path);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);

            if ($rows === []) {
                $this->error(__('Empty or invalid file.'));

                return;
            }

            $headerRow = array_shift($rows);
            $header = array_map(fn ($h): string => $this->normalizeHeader($h ?? ''), $headerRow);

            $newCount = 0;
            $duplicateCount = 0;
            $errorCount = 0;
            $failedRows = [];
            $lineNumber = 1;

            DB::transaction(function () use ($rows, $header, &$newCount, &$duplicateCount, &$errorCount, &$failedRows, &$lineNumber): void {
                $bankImport = BankImport::create([
                    'user_id' => Auth::id(),
                    'new_count' => 0,
                    'duplicate_count' => 0,
                    'error_count' => 0,
                ]);

                foreach ($rows as $row) {
                    $lineNumber++;

                    $row = array_map(fn ($v): ?string => ($v === null || trim((string) $v) === '') ? null : trim((string) $v), $row);
                    $row = array_pad(array_slice($row, 0, count($header)), count($header), null);
                    $rowAssoc = array_combine($header, $row);

                    if ($rowAssoc === false) {
                        continue;
                    }

                    // Raw strings for fingerprinting (before any type conversion)
                    $rawDate = $rowAssoc['date'] ?? '';
                    $rawAmount = $rowAssoc['montant'] ?? $rowAssoc['amount'] ?? '';
                    $rawCounterpartyIban = $rowAssoc['numero de compte contrepartie'] ?? '';
                    $rawStructuredRef = $rowAssoc['communication structuree'] ?? '';
                    $rawFreeRef = $rowAssoc['communication libre'] ?? '';
                    $rawDescription = $rowAssoc['description'] ?? '';

                    $fingerprint = hash('sha256', implode('|', [
                        $rawDate,
                        $rawAmount,
                        $rawCounterpartyIban,
                        $rawStructuredRef,
                        $rawFreeRef,
                        $rawDescription,
                    ]));

                    if (Transaction::where('import_fingerprint', $fingerprint)->exists()) {
                        $duplicateCount++;

                        continue;
                    }

                    try {
                        Transaction::create([
                            'date' => $this->parseDate($rawDate),
                            'description' => $rawDescription ?: null,
                            'amount' => $this->parseAmount($rawAmount),
                            'counterparty_name' => $rowAssoc['nom contrepartie'] ?? null,
                            'counterparty_bank_account' => $rawCounterpartyIban ?: null,
                            'structured_reference' => $rawStructuredRef ?: null,
                            'free_reference' => $rawFreeRef ?: null,
                            'import_fingerprint' => $fingerprint,
                            'bank_import_id' => $bankImport->id,
                        ]);
                        $newCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                        $failedRows[] = [
                            'line' => $lineNumber,
                            'data' => $rowAssoc,
                            'reason' => $e->getMessage(),
                        ];
                    }
                }

                $bankImport->update([
                    'new_count' => $newCount,
                    'duplicate_count' => $duplicateCount,
                    'error_count' => $errorCount,
                    'failed_rows' => $failedRows ?: null,
                ]);
            });

            $this->importModal = false;
            $this->importFile = null;

            $message = __(':count new transaction(s) imported.', ['count' => $newCount]);
            if ($duplicateCount > 0) {
                $message .= ' ' . __(':count duplicate(s) skipped.', ['count' => $duplicateCount]);
            }

            if ($errorCount > 0) {
                $this->warning($message . ' ' . __(':count error(s) — see import history.', ['count' => $errorCount]));
            } else {
                $this->success($message);
            }
        } catch (Exception $e) {
            $this->error(__('Error reading file: :message', ['message' => $e->getMessage()]));
        }
    }

    public function render(): View
    {
        return $this->view([
            'headers' => $this->headers(),
            'transactions' => $this->transactions(),
            'filterChips' => $this->getFilterChips(),
            'reconciledOptions' => [
                ['id' => 'unreconciled', 'name' => __('Unreconciled')],
                ['id' => 'partial',      'name' => __('Partly allocated')],
                ['id' => 'reconciled',   'name' => __('Settled')],
            ],
            'amountDirectionOptions' => [
                ['id' => 'credit', 'name' => __('Credit (incoming)')],
                ['id' => 'debit',  'name' => __('Debit (outgoing)')],
            ],
            'recentImports' => BankImport::with('user')->latest()->limit(10)->get(),
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
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

    public function transactions(): LengthAwarePaginator
    {
        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        return $this->applyFilters(Transaction::with('credits'))
            ->orderBy($col, $dir)
            ->paginate(25);
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
            ->when($this->amountDirection === 'credit', fn (Builder $q): Builder => $q->where('amount', '>', 0))
            ->when($this->amountDirection === 'debit', fn (Builder $q): Builder => $q->where('amount', '<', 0));
    }

    private function closeAllocation(): void
    {
        $this->reset(['allocationModal', 'allocationTransactionId', 'allocations', 'residueReason']);

        unset($this->allocationTransaction, $this->allocationCandidates, $this->remainingToAllocate, $this->stats);
    }

    /**
     * Ce que cette ligne de paiement réclame encore, en euros.
     *
     * Sur un remboursement, `amount_due` porte l'engagement et `amount_paid` ce
     * qui est déjà sorti : la soustraction dit la même chose dans les deux sens.
     */
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

    private function normalizeHeader(string $h): string
    {
        $h = strtolower(trim($h));
        $accents = ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ô' => 'o', 'ö' => 'o', 'î' => 'i', 'ï' => 'i', 'ç' => 'c'];

        return str_replace(array_keys($accents), array_values($accents), $h);
    }

    private function parseAmount(mixed $v): float
    {
        if (empty($v)) {
            return 0;
        }

        if (is_numeric($v)) {
            return (float) $v;
        }

        return (float) str_replace([' ', ','], ['', '.'], (string) $v);
    }

    private function parseDate(mixed $v): ?string
    {
        if (empty($v)) {
            return null;
        }

        if (is_numeric($v)) {
            try {
                return ExcelDate::excelToDateTimeObject($v)->format('Y-m-d');
            } catch (Exception) {
                return null;
            }
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, (string) $v);
                if ($d) {
                    return $d->format('Y-m-d');
                }
            } catch (Exception) {
                continue;
            }
        }

        return null;
    }
};
