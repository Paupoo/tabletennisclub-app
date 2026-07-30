<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\BankImport;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\Shared\Enums\Permission;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
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

    public function bulkDelete(): void
    {
        Gate::authorize(Permission::TransactionsDelete->value);

        $ids = array_map('intval', $this->selected);

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
            $label = $this->reconciledFilter === 'reconciled' ? __('Reconciled') : __('Unreconciled');
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
        ];
    }

    // ==================== Bulk actions ====================

    public function openConfirmDeleteModal(): void
    {
        $ids = array_map('intval', $this->selected);

        $this->reconciledInSelection = Transaction::whereIn('id', $ids)->has('payment')->count();
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

            if (empty($rows)) {
                $this->error(__('Empty or invalid file.'));

                return;
            }

            $headerRow = array_shift($rows);
            $header = array_map(fn ($h) => $this->normalizeHeader($h ?? ''), $headerRow);

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

                    $row = array_map(fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v), $row);
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
                ['id' => 'reconciled',   'name' => __('Reconciled')],
                ['id' => 'unreconciled', 'name' => __('Unreconciled')],
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
            'reconciled' => Transaction::has('payment')->count(),
            'unreconciled' => Transaction::doesntHave('payment')->where('amount', '>', 0)->count(),
        ];
    }

    public function transactions(): LengthAwarePaginator
    {
        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        return Transaction::with('payment')
            ->when($this->search, fn ($q) => $q
                ->where('counterparty_name', 'like', "%{$this->search}%")
                ->orWhere('structured_reference', 'like', "%{$this->search}%")
                ->orWhere('free_reference', 'like', "%{$this->search}%")
                ->orWhere('description', 'like', "%{$this->search}%")
            )
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->when($this->reconciledFilter === 'reconciled', fn ($q) => $q->has('payment'))
            ->when($this->reconciledFilter === 'unreconciled', fn ($q) => $q->doesntHave('payment')->where('amount', '>', 0))
            ->when($this->amountDirection === 'credit', fn ($q) => $q->where('amount', '>', 0))
            ->when($this->amountDirection === 'debit', fn ($q) => $q->where('amount', '<', 0))
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
        return $this->transactions()->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    private function allMatchingTransactionIds(): array
    {
        return Transaction::when($this->search, fn ($q) => $q
            ->where('counterparty_name', 'like', "%{$this->search}%")
            ->orWhere('structured_reference', 'like', "%{$this->search}%")
            ->orWhere('free_reference', 'like', "%{$this->search}%")
            ->orWhere('description', 'like', "%{$this->search}%")
        )
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->when($this->reconciledFilter === 'reconciled', fn ($q) => $q->has('payment'))
            ->when($this->reconciledFilter === 'unreconciled', fn ($q) => $q->doesntHave('payment')->where('amount', '>', 0))
            ->when($this->amountDirection === 'credit', fn ($q) => $q->where('amount', '>', 0))
            ->when($this->amountDirection === 'debit', fn ($q) => $q->where('amount', '<', 0))
            ->pluck('id')
            ->toArray();
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
