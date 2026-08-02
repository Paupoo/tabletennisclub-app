<?php

declare(strict_types=1);

use App\Contracts\DescribesPayment;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\Permission;
use App\Jobs\SendPaymentReminderJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Mail\PaymentInvitationEmail;
use App\Support\Breadcrumb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new class extends Component
{
    use HasBreadcrumbs, Toast, WithPagination;
    use HasBulkActions, HasFilterDrawer;

    public array $batchMatches = [];

    public bool $batchModal = false;

    public bool $bulkCancelRefundModal = false;

    public bool $bulkReminderModal = false;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $eventName = '';

    public string $eventType = '';

    // Drawer filters
    public string $paymentMethod = '';

    public bool $reconcileModal = false;

    public ?int $reconcilePaymentId = null;

    public array $refundBatchMatches = [];

    public bool $refundBatchModal = false;

    public bool $refundModal = false;

    public ?int $refundPaymentId = null;

    public string $search = '';

    public ?int $selectedRefundTransactionId = null;

    public ?int $selectedTransactionId = null;

    public array $sortBy = ['column' => 'created_at', 'direction' => 'desc'];

    public string $statusFilter = 'pending';

    public ?int $userId = null;

    public array $usersSearchList = [];

    public function bulkCancelRefund(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $ids = $this->selectingAllResults
            ? $this->allMatchingPaymentIds()
            : array_map('intval', $this->selected);

        $payments = Payment::whereIn('id', $ids)->where('status', 'to_refund')->get();

        $blocked = $payments->filter(fn (Payment $p) => $p->refund_transaction_id !== null);
        $toCancel = $payments->filter(fn (Payment $p) => $p->refund_transaction_id === null);

        foreach ($toCancel as $payment) {
            $payment->update(['status' => 'paid']);
        }

        $this->bulkCancelRefundModal = false;
        $this->clearSelection();

        if ($blocked->isNotEmpty() && $toCancel->isEmpty()) {
            $this->error(__(':count payment(s) already linked to a bank transaction — cannot cancel refund.', ['count' => $blocked->count()]));
        } elseif ($blocked->isNotEmpty()) {
            $this->warning(__(':cancelled refund(s) cancelled. :blocked skipped (already linked to a transaction).', [
                'cancelled' => $toCancel->count(),
                'blocked' => $blocked->count(),
            ]));
        } else {
            $this->success(__(':count refund(s) cancelled — payments moved back to paid.', ['count' => $toCancel->count()]));
        }
    }

    public function bulkSendReminder(): void
    {
        Gate::authorize(Permission::PaymentsRemind->value);

        $ids = $this->selectingAllResults
            ? $this->allMatchingPaymentIds()
            : array_map('intval', $this->selected);

        foreach ($ids as $id) {
            SendPaymentReminderJob::dispatch($id);
        }

        $this->bulkReminderModal = false;
        $this->clearSelection();
        $this->success(__(':count reminder(s) queued.', ['count' => count($ids)]));
    }

    public function clearFilters(): void
    {
        $this->reset(['paymentMethod', 'dateFrom', 'dateTo', 'userId', 'usersSearchList', 'eventType', 'eventName']);
        $this->resetPage();
    }

    public function confirmBatchReconcile(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $count = 0;

        foreach ($this->batchMatches as $match) {
            DB::transaction(function () use ($match, &$count): void {
                $payment = Payment::find($match['payment_id']);
                $transaction = Transaction::find($match['transaction_id']);

                if (! $payment || ! $transaction) {
                    return;
                }

                $payment->update([
                    'transaction_id' => $transaction->id,
                    'amount_paid' => $transaction->amount,
                    'status' => 'paid',
                ]);

                if ($payment->payable instanceof Subscription) {
                    $this->reconcileSubscription($payment->payable, $transaction->amount);
                }

                $count++;
            });
        }

        $this->batchModal = false;
        $this->batchMatches = [];
        $this->success(__(':count payment(s) reconciled successfully.', ['count' => $count]));
    }

    public function confirmBatchRefundReconcile(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $count = 0;

        foreach ($this->refundBatchMatches as $match) {
            DB::transaction(function () use ($match, &$count): void {
                $payment = Payment::find($match['payment_id']);
                $transaction = Transaction::find($match['transaction_id']);

                if (! $payment || ! $transaction) {
                    return;
                }

                $payment->update([
                    'refund_transaction_id' => $transaction->id,
                    'status' => 'refunded',
                ]);

                $count++;
            });
        }

        $this->refundBatchModal = false;
        $this->refundBatchMatches = [];
        $this->success(__(':count refund(s) confirmed successfully.', ['count' => $count]));
    }

    public function confirmReconcile(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        if (! $this->reconcilePaymentId || ! $this->selectedTransactionId) {
            $this->error(__('Please select a transaction.'));

            return;
        }

        $payment = Payment::findOrFail($this->reconcilePaymentId);
        $transaction = Transaction::findOrFail($this->selectedTransactionId);

        $payment->update([
            'transaction_id' => $transaction->id,
            'amount_paid' => $transaction->amount,
            'status' => 'paid',
        ]);

        if ($payment->payable instanceof Subscription) {
            $this->reconcileSubscription($payment->payable, $transaction->amount);
        } elseif ($payment->payable instanceof TournamentRegistration) {
            $payment->payable->update(['has_paid' => true]);
        }

        $this->reconcileModal = false;
        $this->reconcilePaymentId = null;
        $this->selectedTransactionId = null;
        $this->success(__('Payment reconciled successfully.'));
    }

    public function confirmRefundReconcile(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        if (! $this->refundPaymentId || ! $this->selectedRefundTransactionId) {
            $this->error(__('Please select a transaction.'));

            return;
        }

        DB::transaction(function (): void {
            $payment = Payment::findOrFail($this->refundPaymentId);
            $transaction = Transaction::findOrFail($this->selectedRefundTransactionId);

            $payment->update([
                'refund_transaction_id' => $transaction->id,
                'status' => 'refunded',
            ]);
        });

        $this->refundModal = false;
        $this->refundPaymentId = null;
        $this->selectedRefundTransactionId = null;
        $this->success(__('Refund confirmed successfully.'));
    }

    // ==================== HasFilterDrawer ====================

    public function getFilterChips(): array
    {
        $chips = [];

        if ($this->paymentMethod) {
            $chips[] = ['key' => 'paymentMethod', 'label' => $this->paymentMethod];
        }

        if ($this->dateFrom) {
            $chips[] = ['key' => 'dateFrom', 'label' => __('From: :date', ['date' => $this->dateFrom])];
        }

        if ($this->dateTo) {
            $chips[] = ['key' => 'dateTo', 'label' => __('To: :date', ['date' => $this->dateTo])];
        }

        if ($this->userId) {
            $user = User::find($this->userId);
            $chips[] = ['key' => 'userId', 'label' => $user?->full_name ?? __('Member')];
        }

        if ($this->eventType) {
            $chips[] = ['key' => 'eventType', 'label' => $this->eventTypeLabel($this->eventType)];
        }

        if ($this->eventName) {
            $chips[] = ['key' => 'eventName', 'label' => $this->eventName];
        }

        return $chips;
    }

    public function getTotalMatchingCount(): int
    {
        return $this->payments()->total();
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

    public function openBulkCancelRefundModal(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $this->bulkCancelRefundModal = true;
    }

    // ==================== Bulk actions ====================

    public function openBulkReminderModal(): void
    {
        Gate::authorize(Permission::PaymentsRemind->value);

        $this->bulkReminderModal = true;
    }

    public function openReconcile(int $paymentId): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $this->reconcilePaymentId = $paymentId;
        $this->selectedTransactionId = null;
        $this->reconcileModal = true;
    }

    // ==================== Refund reconciliation ====================

    public function openRefundReconcile(int $paymentId): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $this->refundPaymentId = $paymentId;
        $this->selectedRefundTransactionId = null;
        $this->refundModal = true;
    }

    public function payments(): LengthAwarePaginator
    {
        $col = $this->sortBy['column'];
        $dir = $this->sortBy['direction'];

        $rows = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])
            ->where('status', $this->statusFilter)
            ->when($this->search, fn ($q) => $q
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('payable.user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                )
            )
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->userId, fn ($q) => $q->whereHasMorph(
                'payable',
                [Subscription::class, TournamentRegistration::class, MeetingUser::class],
                fn ($q) => $q->where('user_id', $this->userId)
            ))
            ->when($this->eventType, fn ($q) => $q->where('payable_type', $this->eventType))
            ->when($this->eventName, fn ($q) => $this->applyEventNameFilter($q, $this->eventName))
            ->get()
            ->map(function (Payment $p) {
                $label = $p->payable instanceof DescribesPayment ? $p->payable->getPaymentLabel() : null;

                return (object) [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'member' => $p->payable instanceof DescribesPayment ? $p->payable->getPayerName() : '—',
                    'amount_due' => $p->amount_due,
                    'amount_paid' => $p->amount_paid,
                    'status' => $p->status,
                    'created_at' => $p->created_at,
                    'invitation_counter' => $p->invitation_counter,
                    'iban' => $p->payable?->user?->iban,
                    'event_name' => $label['name'] ?? null,
                    'event_type' => $label['type'] ?? null,
                ];
            });

        $sorted = $dir === 'asc' ? $rows->sortBy($col)->values() : $rows->sortByDesc($col)->values();

        $perPage = 25;
        $page = $this->getPage();

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
                $refMatch = $normalizedPayRef && $normalizedTransRef && $normalizedPayRef === $normalizedTransRef;
                $amountMatch = abs($t->amount - $payment->amount_due) < 0.01;

                $t->match_score = match (true) {
                    $refMatch && $amountMatch => 'perfect',
                    $refMatch => 'reference',
                    $amountMatch => 'amount',
                    default => 'none',
                };

                return $t;
            })
            ->sortByDesc(fn ($t) => match ($t->match_score) {
                'perfect' => 3,
                'reference' => 2,
                'amount' => 1,
                default => 0,
            })
            ->values();
    }

    public function previewBatchMatch(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $pendingPayments = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])
            ->where('status', 'pending')
            ->whereNull('transaction_id')
            ->get();

        $unreconciledTransactions = Transaction::whereDoesntHave('payment')
            ->where('amount', '>', 0)
            ->get()
            ->keyBy(fn ($t) => $this->normalizeReference($t->structured_reference ?? '___' . $t->id));

        $this->batchMatches = [];

        foreach ($pendingPayments as $payment) {
            $normalizedRef = $this->normalizeReference($payment->reference);
            if (! $normalizedRef) {
                continue;
            }

            $transaction = $unreconciledTransactions->get($normalizedRef);

            if ($transaction && abs($transaction->amount - $payment->amount_due) < 0.01) {
                $label = $payment->payable instanceof DescribesPayment ? $payment->payable->getPaymentLabel() : null;

                $this->batchMatches[] = [
                    'payment_id' => $payment->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $payment->reference,
                    'member' => $payment->payable instanceof DescribesPayment ? $payment->payable->getPayerName() : '—',
                    'event_type' => $label['type'] ?? null,
                    'event_name' => $label['name'] ?? null,
                    'amount' => $payment->amount_due,
                    'transaction_date' => $transaction->date,
                    'counterparty' => $transaction->counterparty_name ?? '—',
                ];
                $unreconciledTransactions->forget($normalizedRef);
            }
        }

        if ($this->batchMatches === []) {
            $this->warning(__('No perfect matches found. Import a bank statement or reconcile manually.'));

            return;
        }

        $this->batchModal = true;
    }

    public function previewBatchRefundMatch(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $toRefundPayments = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])
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
                $ibanMatch = $normalizedIban && $this->normalizeIban($transaction->counterparty_bank_account ?? '') === $normalizedIban;
                $amountMatch = abs(abs($transaction->amount) - $payment->amount_paid) < 0.01;

                if ($ibanMatch && $amountMatch) {
                    $label = $payment->payable instanceof DescribesPayment ? $payment->payable->getPaymentLabel() : null;

                    $this->refundBatchMatches[] = [
                        'payment_id' => $payment->id,
                        'transaction_id' => $transaction->id,
                        'reference' => $payment->reference,
                        'member' => $user->full_name,
                        'event_type' => $label['type'] ?? null,
                        'event_name' => $label['name'] ?? null,
                        'iban' => $user->iban,
                        'amount' => $payment->amount_paid,
                        'transaction_date' => $transaction->date,
                        'counterparty' => $transaction->counterparty_name ?? '—',
                    ];
                    $outgoingTransactions->forget($key);
                    break;
                }
            }
        }

        if ($this->refundBatchMatches === []) {
            $this->warning(__('No refund matches found. Import a bank statement containing outgoing transfers or reconcile manually.'));

            return;
        }

        $this->refundBatchModal = true;
    }

    #[Computed]
    public function refundTransactions(): Collection
    {
        $payment = $this->refundPaymentId ? Payment::with(['payable.user'])->find($this->refundPaymentId) : null;
        $user = $payment?->payable?->user;

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

                $ibanMatch = $normalizedIban && $this->normalizeIban($t->counterparty_bank_account ?? '') === $normalizedIban;
                $amountMatch = abs(abs($t->amount) - $payment->amount_paid) < 0.01;

                $t->match_score = match (true) {
                    $ibanMatch && $amountMatch => 'perfect',
                    $ibanMatch => 'iban',
                    $amountMatch => 'amount',
                    default => 'none',
                };

                return $t;
            })
            ->sortByDesc(fn (Transaction $t) => match ($t->match_score) {
                'perfect' => 3,
                'iban' => 2,
                'amount' => 1,
                default => 0,
            })
            ->values();
    }

    public function render(): View
    {
        $payments = $this->payments();

        return $this->view([
            'headers' => $this->headers(),
            'payments' => $payments,
            'filterChips' => $this->getFilterChips(),
            'paymentMethodOptions' => [
                ['id' => 'Cash',    'name' => 'Cash'],
                ['id' => 'Wire',    'name' => 'Wire'],
                ['id' => 'QRCode',  'name' => 'QRCode'],
                ['id' => 'Offered', 'name' => 'Offered'],
            ],
            'eventTypeOptions' => [
                ['id' => Subscription::class,           'name' => __('Subscription')],
                ['id' => TournamentRegistration::class, 'name' => __('Tournament')],
                ['id' => MeetingUser::class,            'name' => __('Meeting')],
            ],
            'pendingTransactions' => $this->reconcileModal ? $this->pendingTransactions() : collect(),
            'currentPayment' => $this->reconcilePaymentId
                ? Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])->find($this->reconcilePaymentId)
                : null,
            'refundTransactions' => $this->refundModal ? $this->refundTransactions : collect(),
            'currentRefundPayment' => $this->refundPaymentId
                ? Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])->find($this->refundPaymentId)
                : null,
            'breadcrumbs' => $this->getBreadcrumbs(),
        ]);
    }

    // ==================== User autocomplete for filter ====================

    public function searchUsers(string $value = ''): void
    {
        if (strlen($value) < 2) {
            $this->usersSearchList = $this->userId
                ? User::where('id', $this->userId)
                    ->get(['id', 'first_name', 'last_name'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->full_name])
                    ->toArray()
                : [];

            return;
        }

        $this->usersSearchList = User::where(fn ($q) => $q
            ->where('first_name', 'like', "%{$value}%")
            ->orWhere('last_name', 'like', "%{$value}%")
        )
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->full_name])
            ->toArray();
    }

    // ==================== Actions ====================

    public function sendReminder(int $paymentId): void
    {
        Gate::authorize(Permission::PaymentsRemind->value);

        $payment = Payment::with(['payable.user'])->find($paymentId);

        if (! $payment?->payable?->user) {
            $this->error(__('Could not find user for this payment.'));

            return;
        }

        Mail::to($payment->payable->user)->send(
            new PaymentInvitationEmail($payment, __('Please settle your payment as soon as possible.'))
        );
        $payment->increment('invitation_counter');

        $this->success(__('Reminder sent to :email.', ['email' => $payment->payable->user->email]));
    }

    // ==================== Data ====================

    #[Computed]
    public function stats(): array
    {
        return [
            'pending_count' => Payment::where('status', 'pending')->count(),
            'pending_total' => round(Payment::where('status', 'pending')->sum('amount_due') / 100, 2),
            'paid_count' => Payment::where('status', 'paid')->count(),
            'paid_total' => round(Payment::where('status', 'paid')->sum('amount_paid') / 100, 2),
            'to_refund_count' => Payment::where('status', 'to_refund')->count(),
            'to_refund_total' => round(Payment::where('status', 'to_refund')->sum('amount_due') / 100, 2),
        ];
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedEventName(): void
    {
        $this->resetPage();
    }

    public function updatedEventType(): void
    {
        $this->resetPage();
    }

    public function updatedPaymentMethod(): void
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

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserId(): void
    {
        $this->resetPage();
    }

    protected function breadcrumbChain(): Breadcrumb
    {
        return Breadcrumb::make()
            ->home()
            ->current(__('Treasury — Payments'));
    }

    // ==================== HasBulkActions ====================

    protected function getPageIds(): array
    {
        return collect($this->payments()->items())
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    private function allMatchingPaymentIds(): array
    {
        return Payment::where('status', $this->statusFilter)
            ->when($this->search, fn ($q) => $q
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhereHas('payable.user', fn ($u) => $u
                    ->where('first_name', 'like', "%{$this->search}%")
                    ->orWhere('last_name', 'like', "%{$this->search}%")
                )
            )
            ->when($this->paymentMethod, fn ($q) => $q->where('payment_method', $this->paymentMethod))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->userId, fn ($q) => $q->whereHasMorph(
                'payable',
                [Subscription::class, TournamentRegistration::class, MeetingUser::class],
                fn ($q) => $q->where('user_id', $this->userId)
            ))
            ->when($this->eventType, fn ($q) => $q->where('payable_type', $this->eventType))
            ->when($this->eventName, fn ($q) => $this->applyEventNameFilter($q, $this->eventName))
            ->pluck('id')
            ->toArray();
    }

    private function applyEventNameFilter(Builder $q, string $name): Builder
    {
        return $q->where(function ($q) use ($name): void {
            $q->whereHasMorph('payable', [Subscription::class], fn ($sub) => $sub
                ->whereHas('season', fn ($s) => $s->where('name', 'like', "%{$name}%"))
            )
                ->orWhereHasMorph('payable', [TournamentRegistration::class], fn ($reg) => $reg
                    ->whereHas('tournament', fn ($t) => $t->where('name', 'like', "%{$name}%"))
                )
                ->orWhereHasMorph('payable', [MeetingUser::class], fn ($mu) => $mu
                    ->whereHas('meeting', fn ($m) => $m->where('title', 'like', "%{$name}%"))
                );
        });
    }

    private function eventTypeLabel(string $type): string
    {
        return match ($type) {
            Subscription::class => __('Subscription'),
            TournamentRegistration::class => __('Tournament'),
            MeetingUser::class => __('Meeting'),
            default => $type,
        };
    }

    private function normalizeIban(string $iban): string
    {
        return strtoupper(str_replace([' ', '-'], '', $iban));
    }

    private function normalizeReference(string $ref): string
    {
        return preg_replace('/[^0-9]/', '', $ref) ?? '';
    }

    /**
     * @return array<class-string, array<int, string>>
     */
    private function payableEagerLoads(): array
    {
        return [
            TournamentRegistration::class => ['user', 'tournament'],
            MeetingUser::class => ['user', 'meeting'],
            Subscription::class => ['user', 'season'],
        ];
    }

    private function reconcileSubscription(Subscription $subscription, float $amount): void
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
};
