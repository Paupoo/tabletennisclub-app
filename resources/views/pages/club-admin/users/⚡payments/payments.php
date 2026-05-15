<?php

declare(strict_types=1);

use App\Mail\PaymentInvitationEmail;
use App\Models\ClubAdmin\Payment\Payment;
use App\Models\ClubAdmin\Payment\Transaction;
use App\Models\ClubAdmin\Subscription\Subscription;
use App\Support\Breadcrumb;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
    use Toast, WithFileUploads, WithPagination;

    public $importFile;
    public bool $importModal             = false;
    public bool $reconcileModal          = false;
    public bool $batchModal              = false;
    public bool $refundModal             = false;
    public bool $refundBatchModal        = false;
    public ?int $reconcilePaymentId      = null;
    public ?int $selectedTransactionId   = null;
    public ?int $refundPaymentId         = null;
    public ?int $selectedRefundTransactionId = null;
    public array $batchMatches           = [];
    public array $refundBatchMatches     = [];
    public string $search                = '';
    public array  $sortBy                = ['column' => 'created_at', 'direction' => 'desc'];
    public string $statusFilter          = 'pending';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSortBy(): void { $this->resetPage(); }

    // ==================== Actions ====================

    public function sendReminder(int $paymentId): void
    {
        $payment = Payment::with(['payable.user'])->find($paymentId);

        if ($payment?->payable instanceof Subscription) {
            $payment->load('payable.season');
        }

        if (! $payment?->payable?->user) {
            $this->error(__('Could not find user for this payment.'));
            return;
        }

        Mail::to($payment->payable->user)->send(new PaymentInvitationEmail($payment));
        $payment->increment('invitation_counter');

        $this->success(__('Reminder sent to :email.', ['email' => $payment->payable->user->email]));
    }

    public function openReconcile(int $paymentId): void
    {
        $this->reconcilePaymentId    = $paymentId;
        $this->selectedTransactionId = null;
        $this->reconcileModal        = true;
    }

    public function confirmReconcile(): void
    {
        if (! $this->reconcilePaymentId || ! $this->selectedTransactionId) {
            $this->error(__('Please select a transaction.'));
            return;
        }

        $payment     = Payment::findOrFail($this->reconcilePaymentId);
        $transaction = Transaction::findOrFail($this->selectedTransactionId);

        $payment->update([
            'transaction_id' => $transaction->id,
            'amount_paid'    => $transaction->amount,
            'status'         => 'paid',
        ]);

        if ($payment->payable instanceof Subscription) {
            $this->reconcileSubscription($payment->payable, $transaction->amount);
        }

        $this->reconcileModal        = false;
        $this->reconcilePaymentId    = null;
        $this->selectedTransactionId = null;
        $this->success(__('Payment reconciled successfully.'));
    }

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
            'pending_count'   => Payment::where('status', 'pending')->count(),
            'pending_total'   => round(Payment::where('status', 'pending')->sum('amount_due') / 100, 2),
            'paid_count'      => Payment::where('status', 'paid')->count(),
            'paid_total'      => round(Payment::where('status', 'paid')->sum('amount_paid') / 100, 2),
            'to_refund_count' => Payment::where('status', 'to_refund')->count(),
            'to_refund_total' => round(Payment::where('status', 'to_refund')->sum('amount_due') / 100, 2),
        ];
    }

    public function headers(): array
    {
        $headers = [
            ['key' => 'reference',  'label' => __('Reference'), 'sortable' => true],
            ['key' => 'member',     'label' => __('Member'),    'sortable' => true],
            ['key' => 'amount_due', 'label' => __('Amount'),    'sortable' => true],
            ['key' => 'created_at', 'label' => __('Date'),      'sortable' => true],
        ];

        if ($this->statusFilter === 'to_refund') {
            $headers[] = ['key' => 'iban', 'label' => __('IBAN'), 'sortable' => false];
        }

        return $headers;
    }

    public function payments(): LengthAwarePaginator
    {
        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        $rows = Payment::with(['payable.user'])
            ->where('status', $this->statusFilter)
            ->when($this->search, fn ($q) => $q
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('payable.user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                )
            )
            ->get()
            ->map(fn (Payment $p) => (object) [
                'id'                 => $p->id,
                'reference'          => $p->reference,
                'member'             => $p->payable?->user
                    ? $p->payable->user->first_name . ' ' . $p->payable->user->last_name
                    : '—',
                'amount_due'         => $p->amount_due,
                'amount_paid'        => $p->amount_paid,
                'status'             => $p->status,
                'created_at'         => $p->created_at,
                'invitation_counter' => $p->invitation_counter,
                'iban'               => $p->payable?->user?->iban,
            ]);

        $sorted = $dir === 'asc' ? $rows->sortBy($col)->values() : $rows->sortByDesc($col)->values();

        $perPage = 25;
        $page    = $this->getPage();

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage),
            $sorted->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    public function pendingTransactions(): Collection
    {
        $payment = $this->reconcilePaymentId ? Payment::find($this->reconcilePaymentId) : null;
        $normalizedPayRef = $payment ? $this->normalizeReference($payment->reference) : null;

        return Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function (Transaction $t) use ($payment, $normalizedPayRef) {
                if (! $payment) {
                    $t->match_score = 'none';
                    return $t;
                }

                $normalizedTransRef = $this->normalizeReference($t->structured_reference ?? '');
                $refMatch    = $normalizedPayRef && $normalizedTransRef && $normalizedPayRef === $normalizedTransRef;
                $amountMatch = abs($t->amount - $payment->amount_due) < 0.01;

                $t->match_score = match (true) {
                    $refMatch && $amountMatch => 'perfect',
                    $refMatch                 => 'reference',
                    $amountMatch              => 'amount',
                    default                   => 'none',
                };

                return $t;
            })
            ->sortByDesc(fn ($t) => match ($t->match_score) {
                'perfect'   => 3,
                'reference' => 2,
                'amount'    => 1,
                default     => 0,
            })
            ->values();
    }

    public function previewBatchMatch(): void
    {
        $pendingPayments = Payment::with(['payable.user'])
            ->where('status', 'pending')
            ->whereNull('transaction_id')
            ->get();

        $unreconciledTransactions = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->get()
            ->keyBy(fn ($t) => $this->normalizeReference($t->structured_reference ?? '___'.$t->id));

        $this->batchMatches = [];

        foreach ($pendingPayments as $payment) {
            $normalizedRef = $this->normalizeReference($payment->reference);
            if (! $normalizedRef) {
                continue;
            }

            $transaction = $unreconciledTransactions->get($normalizedRef);

            if ($transaction && abs($transaction->amount - $payment->amount_due) < 0.01) {
                $this->batchMatches[] = [
                    'payment_id'       => $payment->id,
                    'transaction_id'   => $transaction->id,
                    'reference'        => $payment->reference,
                    'member'           => $payment->payable?->user
                        ? $payment->payable->user->first_name . ' ' . $payment->payable->user->last_name
                        : '—',
                    'amount'           => $payment->amount_due,
                    'transaction_date' => $transaction->date,
                    'counterparty'     => $transaction->counterparty_name ?? '—',
                ];
                // On retire la transaction du pool pour éviter les doublons
                $unreconciledTransactions->forget($normalizedRef);
            }
        }

        if (empty($this->batchMatches)) {
            $this->warning(__('No perfect matches found. Import a bank statement or reconcile manually.'));
            return;
        }

        $this->batchModal = true;
    }

    public function confirmBatchReconcile(): void
    {
        $count = 0;

        foreach ($this->batchMatches as $match) {
            DB::transaction(function () use ($match, &$count): void {
                $payment     = Payment::find($match['payment_id']);
                $transaction = Transaction::find($match['transaction_id']);

                if (! $payment || ! $transaction) {
                    return;
                }

                $payment->update([
                    'transaction_id' => $transaction->id,
                    'amount_paid'    => $transaction->amount,
                    'status'         => 'paid',
                ]);

                if ($payment->payable instanceof Subscription) {
                    $this->reconcileSubscription($payment->payable, $transaction->amount);
                }

                $count++;
            });
        }

        $this->batchModal   = false;
        $this->batchMatches = [];
        $this->success(__(':count payment(s) reconciled successfully.', ['count' => $count]));
    }

    // ==================== Refund reconciliation ====================

    public function openRefundReconcile(int $paymentId): void
    {
        $this->refundPaymentId             = $paymentId;
        $this->selectedRefundTransactionId = null;
        $this->refundModal                 = true;
    }

    public function confirmRefundReconcile(): void
    {
        if (! $this->refundPaymentId || ! $this->selectedRefundTransactionId) {
            $this->error(__('Please select a transaction.'));

            return;
        }

        DB::transaction(function (): void {
            $payment     = Payment::findOrFail($this->refundPaymentId);
            $transaction = Transaction::findOrFail($this->selectedRefundTransactionId);

            $payment->update([
                'refund_transaction_id' => $transaction->id,
                'status'                => 'refunded',
            ]);
        });

        $this->refundModal                 = false;
        $this->refundPaymentId             = null;
        $this->selectedRefundTransactionId = null;
        $this->success(__('Refund confirmed successfully.'));
    }

    public function previewBatchRefundMatch(): void
    {
        $toRefundPayments = Payment::with(['payable.user'])
            ->where('status', 'to_refund')
            ->whereNull('refund_transaction_id')
            ->get();

        $outgoingTransactions = Transaction::whereDoesntHave('refundPayment')
            ->where('amount', '<', 0)
            ->get();

        $this->refundBatchMatches = [];

        foreach ($toRefundPayments as $payment) {
            $user = $payment->payable?->user;
            if (! $user) {
                continue;
            }

            $normalizedIban = $this->normalizeIban($user->iban ?? '');

            foreach ($outgoingTransactions as $key => $transaction) {
                $ibanMatch   = $normalizedIban && $this->normalizeIban($transaction->counterparty_bank_account ?? '') === $normalizedIban;
                $amountMatch = abs(abs($transaction->amount) - $payment->amount_paid) < 0.01;

                if ($ibanMatch && $amountMatch) {
                    $this->refundBatchMatches[] = [
                        'payment_id'       => $payment->id,
                        'transaction_id'   => $transaction->id,
                        'reference'        => $payment->reference,
                        'member'           => $user->full_name,
                        'iban'             => $user->iban,
                        'amount'           => $payment->amount_paid,
                        'transaction_date' => $transaction->date,
                        'counterparty'     => $transaction->counterparty_name ?? '—',
                    ];
                    $outgoingTransactions->forget($key);
                    break;
                }
            }
        }

        if (empty($this->refundBatchMatches)) {
            $this->warning(__('No refund matches found. Import a bank statement containing outgoing transfers or reconcile manually.'));

            return;
        }

        $this->refundBatchModal = true;
    }

    public function confirmBatchRefundReconcile(): void
    {
        $count = 0;

        foreach ($this->refundBatchMatches as $match) {
            DB::transaction(function () use ($match, &$count): void {
                $payment     = Payment::find($match['payment_id']);
                $transaction = Transaction::find($match['transaction_id']);

                if (! $payment || ! $transaction) {
                    return;
                }

                $payment->update([
                    'refund_transaction_id' => $transaction->id,
                    'status'                => 'refunded',
                ]);

                $count++;
            });
        }

        $this->refundBatchModal   = false;
        $this->refundBatchMatches = [];
        $this->success(__(':count refund(s) confirmed successfully.', ['count' => $count]));
    }

    #[Computed]
    public function refundTransactions(): Collection
    {
        $payment = $this->refundPaymentId ? Payment::with(['payable.user'])->find($this->refundPaymentId) : null;
        $user    = $payment?->payable?->user;

        $normalizedIban = $this->normalizeIban($user?->iban ?? '');

        return Transaction::whereDoesntHave('refundPayment')
            ->where('amount', '<', 0)
            ->orderBy('date', 'desc')
            ->get()
            ->map(function (Transaction $t) use ($payment, $normalizedIban): Transaction {
                if (! $payment) {
                    $t->match_score = 'none';

                    return $t;
                }

                $ibanMatch   = $normalizedIban && $this->normalizeIban($t->counterparty_bank_account ?? '') === $normalizedIban;
                $amountMatch = abs(abs($t->amount) - $payment->amount_paid) < 0.01;

                $t->match_score = match (true) {
                    $ibanMatch && $amountMatch => 'perfect',
                    $ibanMatch                 => 'iban',
                    $amountMatch               => 'amount',
                    default                    => 'none',
                };

                return $t;
            })
            ->sortByDesc(fn (Transaction $t) => match ($t->match_score) {
                'perfect' => 3,
                'iban'    => 2,
                'amount'  => 1,
                default   => 0,
            })
            ->values();
    }

    private function reconcileSubscription(Subscription $subscription, int $amount): void
    {
        $subscription->update(['amount_paid' => $amount]);

        $status = $subscription->getStatus();

        if ($status === 'paid') {
            return;
        }

        if ($status === 'pending') {
            $subscription->confirm();
        }

        $subscription->markAsPaid();
    }

    public function render(): View
    {
        return $this->view([
            'headers'              => $this->headers(),
            'payments'             => $this->payments(),
            'pendingTransactions'  => $this->reconcileModal ? $this->pendingTransactions() : collect(),
            'currentPayment'       => $this->reconcilePaymentId
                ? Payment::with(['payable.user'])->find($this->reconcilePaymentId)
                : null,
            'refundTransactions'   => $this->refundModal ? $this->refundTransactions : collect(),
            'currentRefundPayment' => $this->refundPaymentId
                ? Payment::with(['payable.user'])->find($this->refundPaymentId)
                : null,
            'breadcrumbs'          => Breadcrumb::make()
                ->home()
                ->current(__('Payments'))
                ->toArray(),
        ]);
    }

    private function normalizeReference(string $ref): string
    {
        return preg_replace('/[^0-9]/', '', $ref) ?? '';
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(str_replace([' ', '-'], '', $iban));
    }

    // ==================== Helpers ====================

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
