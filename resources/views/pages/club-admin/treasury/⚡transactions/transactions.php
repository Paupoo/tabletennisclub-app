<?php

declare(strict_types=1);

use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Support\Breadcrumb;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

new class extends Component
{
    use Toast, WithFileUploads, WithPagination, HasBreadcrumbs;
    use HasBulkActions, HasFilterDrawer;

    public $importFile;
    public bool $importModal           = false;
    public bool $confirmDeleteModal    = false;
    public int  $reconciledInSelection = 0;
    public string $search  = '';
    public array  $sortBy  = ['column' => 'date', 'direction' => 'desc'];

    // Drawer filters
    public string $dateFrom          = '';
    public string $dateTo            = '';
    public string $reconciledFilter  = '';
    public string $amountDirection   = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }
    public function updatedReconciledFilter(): void { $this->resetPage(); }
    public function updatedAmountDirection(): void { $this->resetPage(); }

    // ==================== HasBulkActions ====================

    protected function getPageIds(): array
    {
        return $this->transactions()->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    public function getTotalMatchingCount(): int
    {
        return $this->transactions()->total();
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
            $label   = $this->reconciledFilter === 'reconciled' ? __('Reconciled') : __('Unreconciled');
            $chips[] = ['key' => 'reconciledFilter', 'label' => $label];
        }

        if ($this->amountDirection) {
            $label   = $this->amountDirection === 'credit' ? __('Credit') : __('Debit');
            $chips[] = ['key' => 'amountDirection', 'label' => $label];
        }

        return $chips;
    }

    public function clearFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo', 'reconciledFilter', 'amountDirection']);
        $this->resetPage();
    }

    // ==================== Bulk actions ====================

    public function openConfirmDeleteModal(): void
    {
        $ids = array_map('intval', $this->selected);

        $this->reconciledInSelection = Transaction::whereIn('id', $ids)->has('payment')->count();
        $this->confirmDeleteModal    = true;
    }

    public function bulkDelete(): void
    {
        $ids = array_map('intval', $this->selected);

        if ($this->selectingAllResults) {
            $ids = $this->allMatchingTransactionIds();
        }

        Transaction::whereIn('id', $ids)->delete();

        $this->confirmDeleteModal    = false;
        $this->reconciledInSelection = 0;
        $this->clearSelection();
        $this->success(__(':count transaction(s) deleted.', ['count' => count($ids)]));
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
            ->when($this->reconciledFilter === 'unreconciled', fn ($q) => $q->doesntHave('payment'))
            ->when($this->amountDirection === 'credit', fn ($q) => $q->where('amount', '>', 0))
            ->when($this->amountDirection === 'debit', fn ($q) => $q->where('amount', '<', 0))
            ->pluck('id')
            ->toArray();
    }

    // ==================== Actions ====================

    public function processImport(): void
    {
        $this->validate(['importFile' => 'required|file|mimes:ods,xlsx,xls,csv,txt']);

        $path = $this->importFile->getRealPath();

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, true, true);

            if (empty($rows)) {
                $this->error(__('Empty or invalid file.'));
                return;
            }

            $headerRow = array_shift($rows);
            $header    = array_map(fn ($h) => $this->normalizeHeader($h ?? ''), $headerRow);

            $importedCount = 0;

            foreach ($rows as $row) {
                $row      = array_map(fn ($v) => ($v === null || trim((string) $v) === '') ? null : trim((string) $v), $row);
                $row      = array_pad(array_slice($row, 0, count($header)), count($header), null);
                $rowAssoc = array_combine($header, $row);

                if ($rowAssoc === false) {
                    continue;
                }

                try {
                    Transaction::create([
                        'date'                      => $this->parseDate($rowAssoc['date'] ?? null),
                        'description'               => $rowAssoc['description'] ?? null,
                        'amount'                    => $this->parseAmount($rowAssoc['montant'] ?? $rowAssoc['amount'] ?? null),
                        'counterparty_name'         => $rowAssoc['nom contrepartie'] ?? null,
                        'counterparty_bank_account' => $rowAssoc['numero de compte contrepartie'] ?? null,
                        'structured_reference'      => $rowAssoc['communication structuree'] ?? null,
                        'free_reference'            => $rowAssoc['communication libre'] ?? null,
                    ]);
                    $importedCount++;
                } catch (\Exception) {
                    continue;
                }
            }

            $this->importModal = false;
            $this->importFile  = null;
            $this->success(__(':count transactions imported successfully.', ['count' => $importedCount]));
        } catch (\Exception $e) {
            $this->error(__('Error reading file: :message', ['message' => $e->getMessage()]));
        }
    }

    // ==================== Data ====================

    #[Computed]
    public function stats(): array
    {
        return [
            'total'        => Transaction::count(),
            'reconciled'   => Transaction::has('payment')->count(),
            'unreconciled' => Transaction::doesntHave('payment')->where('amount', '>', 0)->count(),
        ];
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
            ->when($this->reconciledFilter === 'unreconciled', fn ($q) => $q->doesntHave('payment'))
            ->when($this->amountDirection === 'credit', fn ($q) => $q->where('amount', '>', 0))
            ->when($this->amountDirection === 'debit', fn ($q) => $q->where('amount', '<', 0))
            ->orderBy($col, $dir)
            ->paginate(25);
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Treasury — Transactions'));
    }

    public function render(): View
    {
        return $this->view([
            'headers'      => $this->headers(),
            'transactions' => $this->transactions(),
            'filterChips'  => $this->getFilterChips(),
            'reconciledOptions' => [
                ['id' => 'reconciled',   'name' => __('Reconciled')],
                ['id' => 'unreconciled', 'name' => __('Unreconciled')],
            ],
            'amountDirectionOptions' => [
                ['id' => 'credit', 'name' => __('Credit (incoming)')],
                ['id' => 'debit',  'name' => __('Debit (outgoing)')],
            ],
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    private function normalizeHeader(string $h): string
    {
        $h       = strtolower(trim($h));
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
            } catch (\Exception) {
                return null;
            }
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd.m.Y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, (string) $v);
                if ($d) {
                    return $d->format('Y-m-d');
                }
            } catch (\Exception) {
                continue;
            }
        }

        return null;
    }
};
