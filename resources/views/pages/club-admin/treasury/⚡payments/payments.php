<?php

declare(strict_types=1);

use App\Actions\ClubAdmin\Payments\AllocateTransactionAction;
use App\Actions\ClubAdmin\Subscriptions\RequestSubscriptionRefundAction;
use App\Contracts\DescribesPayment;
use App\Domains\Bar\Models\BarOrder;
use App\Domains\ClubAdmin\Payment\Models\Payment;
use App\Domains\ClubAdmin\Payment\Models\Transaction;
use App\Domains\ClubAdmin\Payment\Services\TransactionMatcher;
use App\Domains\ClubAdmin\Subscriptions\Models\Subscription;
use App\Domains\ClubAdmin\Users\Models\User;
use App\Domains\Competitions\Interclub\Models\Club;
use App\Domains\Competitions\Tournament\Models\TournamentRegistration;
use App\Domains\Meetings\Models\MeetingUser;
use App\Domains\Shared\Enums\Permission;
use App\Jobs\SendPaymentReminderJob;
use App\Livewire\Concerns\HasBreadcrumbs;
use App\Livewire\Concerns\HasBulkActions;
use App\Livewire\Concerns\HasFilterDrawer;
use App\Mail\PaymentInvitationEmail;
use App\Support\Breadcrumb;
use App\Support\LocaleSort;
use App\Support\Treasury\SepaRemittance;
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

    public float $refundRequestAmount = 0.0;

    /** Le compte à rembourser : celui qui a versé, pas celui du membre. */
    public string $refundRequestIban = '';

    public bool $refundRequestModal = false;

    public ?int $refundRequestPaymentId = null;

    public string $refundRequestReason = '';

    /** Ce qu'un rapprochement vient de laisser sur le virement, s'il reste quelque chose. */
    public ?array $residueNotice = null;

    public string $search = '';

    /** Les clés cochées : par défaut, les seuls appariements dont le barème est certain. */
    public array $selectedBatchMatches = [];

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
            : array_map(intval(...), $this->selected);

        $payments = Payment::whereIn('id', $ids)->where('status', 'to_refund')->get();

        // Ce qui bloque l'annulation, c'est l'argent déjà sorti — pas une
        // colonne de liaison que plus personne n'écrit.
        $blocked = $payments->filter(fn (Payment $p): bool => (float) $p->amount_paid > 0.0);
        $toCancel = $payments->filter(fn (Payment $p): bool => (float) $p->amount_paid <= 0.0);

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
            : array_map(intval(...), $this->selected);

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

        $selected = array_map(intval(...), $this->selectedBatchMatches);

        foreach ($this->batchMatches as $key => $match) {
            if (! in_array($key, $selected, true)) {
                continue;
            }

            DB::transaction(function () use ($match, &$count): void {
                $payment = Payment::find($match['payment_id']);
                $transaction = Transaction::find($match['transaction_id']);

                if (! $payment || ! $transaction) {
                    return;
                }

                // Le montant vient de la sélection : c'est celui que le
                // trésorier a sous les yeux dans la modale, et le recalculer
                // ici ferait diverger ce qu'il confirme de ce qui est écrit.
                (new AllocateTransactionAction)($transaction, [
                    $payment->id => (float) $match['amount'],
                ]);

                $count++;
            });
        }

        $this->batchModal = false;
        $this->batchMatches = [];
        $this->selectedBatchMatches = [];
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

                (new AllocateTransactionAction)($transaction, [
                    $payment->id => $this->allocatableAmount($payment, $transaction),
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

        try {
            (new AllocateTransactionAction)($transaction, [
                $payment->id => $this->allocatableAmount($payment, $transaction),
            ]);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->reconcileModal = false;
        $this->reconcilePaymentId = null;
        $this->selectedTransactionId = null;

        // Ce qui reste sur le virement doit être dit maintenant. Sans ça, le
        // trésorier clique, s'en va, et cet argent dort sans que personne sache
        // qu'il appartient à quelqu'un.
        $residue = abs($transaction->fresh()->residue());

        // Un bandeau plutôt qu'un toast : trois secondes ne suffisent pas à
        // décider quoi faire de cent euros, et le trésorier ne retient pas ses
        // propres rapprochements.
        $this->residueNotice = $residue > 0.0
            ? ['amount' => $residue, 'transaction_id' => $transaction->id]
            : null;

        $this->success(__('Payment reconciled successfully.'));
    }

    public function confirmRefundReconcile(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        if (! $this->refundPaymentId || ! $this->selectedRefundTransactionId) {
            $this->error(__('Please select a transaction.'));

            return;
        }

        $payment = Payment::findOrFail($this->refundPaymentId);
        $transaction = Transaction::findOrFail($this->selectedRefundTransactionId);

        try {
            (new AllocateTransactionAction)($transaction, [
                $payment->id => $this->allocatableAmount($payment, $transaction),
            ]);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return;
        }

        $this->refundModal = false;
        $this->refundPaymentId = null;
        $this->selectedRefundTransactionId = null;
        $this->success(__('Refund confirmed successfully.'));
    }

    /**
     * Ouvre un remboursement sur une ligne, à la demande du membre.
     *
     * Le geste manquait à la trésorerie : un remboursement ne pouvait naître
     * que d'un changement de facture côté secrétariat. Le membre qui a payé
     * deux fois n'a rien changé à sa facture.
     */
    public function confirmRefundRequest(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $payment = Payment::find($this->refundRequestPaymentId);

        if (! $payment || ! $payment->payable instanceof Subscription) {
            $this->error(__('A refund can only be opened on a membership fee.'));

            return;
        }

        $reason = trim($this->refundRequestReason);

        if ($reason === '') {
            $this->error(__('A reason is required to open a refund.'));

            return;
        }

        // Ce qui est réellement rentré, net des remboursements déjà engagés.
        // `netAmountPaid()` existait pour ce calcul — sa docstring prévient que
        // s'en passer rembourserait deux fois — sans qu'aucun écran l'appelle.
        $ceiling = $payment->payable->netAmountPaid();

        if ($this->refundRequestAmount <= 0.0 || $this->refundRequestAmount > $ceiling) {
            $this->error(__('A refund cannot exceed the :amount € actually received.', [
                'amount' => number_format($ceiling, 2, ',', ' '),
            ]));

            return;
        }

        (new RequestSubscriptionRefundAction)(
            $payment->payable,
            $this->refundRequestAmount,
            $reason,
            targetIban: $this->refundRequestIban !== '' ? $this->refundRequestIban : null,
        );

        $this->reset(['refundRequestModal', 'refundRequestPaymentId', 'refundRequestAmount', 'refundRequestReason', 'refundRequestIban']);
        $this->success(__('Refund opened. The treasury has been notified.'));
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
        $this->residueNotice = null;
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

    public function openRefundRequest(int $paymentId): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $payment = Payment::find($paymentId);

        $this->refundRequestPaymentId = $paymentId;
        $this->refundRequestReason = '';

        // Sur un trop-perçu, les deux valeurs sont déductibles : l'excédent se
        // calcule, et le compte se lit sur le virement qui l'a produit. Les
        // faire saisir reviendrait à demander au trésorier de retrouver ce que
        // le système a sous la main.
        $overpaid = $payment instanceof Payment && $payment->isOverpaid();

        $this->refundRequestAmount = match (true) {
            $overpaid => $payment->overpayment(),
            $payment?->payable instanceof Subscription => $payment->payable->netAmountPaid(),
            default => 0.0,
        };

        $this->refundRequestIban = (string) ($overpaid
            ? $this->payingAccountOf($payment)
            : $payment?->payable?->user?->iban ?? '');

        $this->refundRequestModal = true;
    }

    public function payments(): LengthAwarePaginator
    {
        $col = $this->sortColumn();
        $dir = $this->sortBy['direction'];

        $rows = $this->applyFilters(
            Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])
                ->tap(fn (Builder $q): Builder => $this->applyTab($q))
        )
            ->get()
            ->map(function (Payment $p) {
                $label = $p->payable instanceof DescribesPayment ? $p->payable->getPaymentLabel() : null;

                return (object) [
                    'id' => $p->id,
                    'reference' => $p->reference,
                    'member' => $p->payable instanceof DescribesPayment ? $p->payable->getPayerName() : '—',
                    'amount_due' => $p->amount_due,
                    'amount_paid' => $p->amount_paid,
                    // Ce qui reste, et d'où vient ce qui est déjà là. Le
                    // trésorier ne retient pas ses rapprochements : un solde
                    // sans son origine ne se vérifie pas.
                    'balance' => $p->balance(),
                    // Le net, jamais « 220 sur 120 » : c'est ce que le club
                    // détient et devra rendre.
                    'overpayment' => $p->overpayment(),
                    'refund_iban' => $p->refund_iban,
                    // Le texte que le payeur lira sur son extrait. Le trésorier
                    // fait le virement dans sa banque, pas ici : il lui faut
                    // sous les yeux.
                    'remittance' => $p->payment_method === 'refund'
                        ? SepaRemittance::forOverpayment(
                            club: Club::ourClub()->first()?->name ?? 'CTT Ottignies-Blocry',
                            event: $label['name'] ?? '',
                            member: $p->payable instanceof DescribesPayment ? $p->payable->getPayerName() : '',
                        )
                        : null,
                    'is_partially_paid' => $p->isPartiallyPaid(),
                    'status' => $p->status,
                    'created_at' => $p->created_at,
                    'invitation_counter' => $p->invitation_counter,
                    'last_reminded_at' => $p->last_reminded_at,
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
        $payment = $this->reconcilePaymentId
            ? Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->reconcileEagerLoads())])->find($this->reconcilePaymentId)
            : null;

        // Ce qui reste à placer, pas ce qui n'a pas de paiement attaché : depuis
        // que le geste passe par l'action, le lien `payment` n'est plus écrit,
        // et une ligne déjà entièrement affectée reviendrait dans la liste.
        $candidates = Transaction::where('amount', '>', 0)
            ->whereNull('settled_at')
            ->orderBy('date', 'desc')
            ->get()
            ->filter(fn (Transaction $transaction): bool => abs($transaction->residue()) > 0.001)
            ->values();

        return $payment
            ? (new TransactionMatcher)->rank($payment, $candidates)
            : $candidates;
    }

    public function previewBatchMatch(): void
    {
        Gate::authorize(Permission::PaymentsReconcile->value);

        $pendingPayments = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])
            ->where('status', 'pending')
            ->get();

        // Ce qu'il reste à placer sur chaque ligne de relevé, en centimes. Le
        // compteur vit pour toute la passe : une même transaction peut servir
        // plusieurs paiements, et chacun ne prend que ce qui reste.
        $remaining = [];

        // `groupBy`, pas `keyBy` : deux virements portant la même communication
        // structurée sont deux versements sur la même créance, pas un doublon.
        // L'index par clé unique n'en gardait qu'un, silencieusement.
        //
        // Une ligne sans communication structurée n'entre pas : le masse ne
        // tranche que sur l'identifiant que le club a lui-même émis. Les
        // rapprochements par nom ou IBAN se choisissent, ils ne se décident pas.
        $byReference = Transaction::where('amount', '>', 0)
            ->whereNull('settled_at')
            ->whereNotNull('structured_reference')
            ->get()
            ->filter(function (Transaction $transaction) use (&$remaining): bool {
                $remaining[$transaction->id] = (int) round(abs($transaction->residue()) * 100);

                return $remaining[$transaction->id] > 0;
            })
            ->groupBy(fn (Transaction $transaction): string => $this->normalizeReference((string) $transaction->structured_reference));

        $this->batchMatches = [];

        foreach ($pendingPayments as $payment) {
            $normalizedRef = $this->normalizeReference($payment->reference);

            if (! $normalizedRef) {
                continue;
            }

            $candidates = $byReference->get($normalizedRef);

            if ($candidates === null) {
                continue;
            }

            // Le solde restant, en centimes. Le montant ne décide plus de *qui*
            // — la référence l'a déjà fait — seulement de *combien*.
            $balance = (int) round(((float) $payment->amount_due - (float) $payment->amount_paid) * 100);

            $label = $payment->payable instanceof DescribesPayment ? $payment->payable->getPaymentLabel() : null;

            foreach ($candidates as $transaction) {
                if ($balance <= 0) {
                    break;
                }

                $take = min($balance, $remaining[$transaction->id]);

                if ($take <= 0) {
                    continue;
                }

                // Parfait : la référence **et** le montant, sur une créance et
                // un virement encore intacts. Tout le reste — un versement
                // partiel, un second virement sur la même référence — est
                // défendable mais demande un regard.
                $exact = $balance === $remaining[$transaction->id]
                    && (int) round((float) $payment->amount_paid * 100) === 0
                    && (int) round(abs((float) $transaction->allocated_amount) * 100) === 0;

                $this->batchMatches[] = [
                    'exact' => $exact,
                    'reason' => $exact
                        ? __('reference and amount match exactly')
                        : __('partial payment — :amount € owed', [
                            'amount' => number_format((float) $payment->amount_due - (float) $payment->amount_paid, 2, ',', ' '),
                        ]),
                    'payment_id' => $payment->id,
                    'transaction_id' => $transaction->id,
                    'reference' => $payment->reference,
                    'member' => $payment->payable instanceof DescribesPayment ? $payment->payable->getPayerName() : '—',
                    'event_type' => $label['type'] ?? null,
                    'event_name' => $label['name'] ?? null,
                    'amount' => round($take / 100, 2),
                    'transaction_date' => $transaction->date,
                    'counterparty' => $transaction->counterparty_name ?? '—',
                ];

                $remaining[$transaction->id] -= $take;
                $balance -= $take;
            }
        }

        if ($this->batchMatches === []) {
            $this->warning(__('No reference matches found. Import a bank statement or reconcile manually.'));

            return;
        }

        // Cochés d'office : ceux dont le barème est certain. Les autres
        // attendent un geste — quarante lignes et un seul bouton, personne ne
        // lit, et de l'argent se place tout seul au mauvais endroit.
        $this->selectedBatchMatches = collect($this->batchMatches)
            ->filter(fn (array $match): bool => $match['exact'])
            ->keys()
            ->map(fn (int $key): string => (string) $key)
            ->all();

        $this->batchModal = true;
    }

    public function previewBatchRefundMatch(): void
    {
        Gate::authorize(Permission::PaymentsRefund->value);

        $toRefundPayments = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])
            ->where('status', 'to_refund')
            ->get();

        // Un virement sortant qui a encore quelque chose à placer. Le lien
        // `refundPayment` ne dit plus rien : personne ne l'écrit depuis que le
        // geste passe par l'action.
        $outgoingTransactions = Transaction::where('amount', '<', 0)
            ->whereNull('settled_at')
            ->get()
            ->filter(fn (Transaction $transaction): bool => abs($transaction->residue()) > 0.001)
            ->values();

        $this->refundBatchMatches = [];

        foreach ($toRefundPayments as $payment) {
            $user = $payment->payable?->user;
            if (! $user) {
                continue;
            }

            // Le compte visé par le remboursement, et l'IBAN du membre à
            // défaut. Un trop-perçu se rend au compte qui a versé — souvent
            // celui d'un tuteur — et comparer l'IBAN du membre n'aurait jamais
            // rien reconnu dans ce cas.
            $normalizedIban = $this->normalizeIban($payment->refund_iban ?? $user->iban ?? '');

            foreach ($outgoingTransactions as $key => $transaction) {
                $ibanMatch = $normalizedIban && $this->normalizeIban($transaction->counterparty_bank_account ?? '') === $normalizedIban;
                // `amount_due` : sur une ligne de remboursement c'est
                // l'engagement, et `amount_paid` ne vaut plus que ce qui est
                // déjà sorti — zéro tant que le virement n'est pas fait, donc
                // exactement les lignes que cet appariement cherche.
                $amountMatch = abs(abs($transaction->amount) - $payment->amount_due) < 0.01;

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
                        'amount' => $payment->amount_due,
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
        $payment = $this->refundPaymentId
            ? Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->reconcileEagerLoads())])->find($this->refundPaymentId)
            : null;

        // Un remboursement sort du compte du club : les candidates sont les
        // débits, mais le barème est le même — c'est le même membre qu'on
        // cherche au bout du virement.
        $candidates = Transaction::where('amount', '<', 0)
            ->whereNull('settled_at')
            ->orderBy('date', 'desc')
            ->get()
            ->filter(fn (Transaction $transaction): bool => abs($transaction->residue()) > 0.001)
            ->values();

        return $payment
            ? (new TransactionMatcher)->rank($payment, $candidates)
            : $candidates;
    }

    public function render(): View
    {
        $payments = $this->payments();

        return $this->view([
            'headers' => $this->headers(),
            'payments' => $payments,
            'filterChips' => $this->getFilterChips(),
            // Both lists were in the order someone happened to type them.
            'paymentMethodOptions' => LocaleSort::byKey(collect([
                ['id' => 'Cash',    'name' => 'Cash'],
                ['id' => 'Wire',    'name' => 'Wire'],
                ['id' => 'QRCode',  'name' => 'QRCode'],
                ['id' => 'Offered', 'name' => 'Offered'],
            ]), 'name')->all(),
            'eventTypeOptions' => LocaleSort::byKey(collect([
                ['id' => Subscription::class,           'name' => __('Subscription')],
                ['id' => TournamentRegistration::class, 'name' => __('Tournament')],
                ['id' => MeetingUser::class,            'name' => __('Meeting')],
                ['id' => BarOrder::class,               'name' => __('Bar')],
            ]), 'name')->all(),
            'pendingTransactions' => $this->reconcileModal ? $this->pendingTransactions() : collect(),
            'currentPayment' => $this->reconcilePaymentId
                ? Payment::with([
                    'payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads()),
                    // L'historique des affectations : un trésorier ne retient
                    // pas ses rapprochements, et un solde dont on ne peut pas
                    // remonter l'origine ne se vérifie pas.
                    'credits.transaction',
                ])->find($this->reconcilePaymentId)
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
                    ->map(fn ($u): array => ['id' => $u->id, 'name' => $u->full_name])
                    ->toArray()
                : [];

            return;
        }

        $matches = User::where(fn ($q) => $q
            ->where('first_name', 'like', "%{$value}%")
            ->orWhere('last_name', 'like', "%{$value}%")
        )
            // Which ten, decided in the database on a stable key; how they are
            // then shown, decided on the label — « Prénom Nom » — because a list
            // ordered on a surname it never displays reads as unordered.
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($u): array => ['id' => $u->id, 'name' => $u->full_name]);

        $this->usersSearchList = LocaleSort::byKey($matches, 'name')->all();
    }

    // ==================== Actions ====================

    public function sendReminder(int $paymentId): void
    {
        Gate::authorize(Permission::PaymentsRemind->value);

        $payment = Payment::with(['payable' => fn (MorphTo $m) => $m->morphWith($this->payableEagerLoads())])->find($paymentId);

        if (! $payment?->payable?->user) {
            $this->error(__('Could not find user for this payment.'));

            return;
        }

        // Only the tab filter used to keep this button away from settled payments,
        // which is a rendering detail: a stale Livewire request or a double click
        // during a tab change was enough to dun a member who had already paid.
        if ($payment->status !== 'pending') {
            $this->error(__('This payment is settled: there is nothing to chase.'));

            return;
        }

        Mail::to($payment->payable->user)->send(
            new PaymentInvitationEmail($payment, __('Please settle your payment as soon as possible.'))
        );
        // One write, not two: the counter and the date describe the same event, and
        // an increment followed by a separate save can leave the count raised with
        // no date behind it.
        $payment->forceFill([
            'invitation_counter' => $payment->invitation_counter + 1,
            'last_reminded_at' => now(),
        ])->save();

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
            // Le net, comme la colonne de l'onglet : ce que le club détient en
            // trop, jamais la somme encaissée. Même définition que applyTab(),
            // sans quoi la carte et l'onglet compteraient deux ensembles.
            'overpaid_count' => $this->overpaid()->count(),
            'overpaid_total' => round(((int) $this->overpaid()->sum(DB::raw('amount_paid - amount_due'))) / 100, 2),
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
            ->map(fn ($id): string => (string) $id)
            ->toArray();
    }

    private function allMatchingPaymentIds(): array
    {
        return $this->applyFilters($this->scopedToTab())
            ->pluck('id')
            ->toArray();
    }

    /**
     * Ce qu'on peut raisonnablement affecter de cette ligne à ce paiement.
     *
     * Le plus petit des deux restes : ce que la transaction n'a pas encore
     * placé, et ce que le paiement réclame encore. Jamais au-delà du solde —
     * dépasser reconnaît un trop-perçu, et c'est une décision, pas un défaut.
     */
    private function allocatableAmount(Payment $payment, Transaction $transaction): float
    {
        $residue = abs($transaction->residue());
        $balance = max(0.0, round((float) $payment->amount_due - (float) $payment->amount_paid, 2));

        return round(min($residue, $balance), 2);
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

    /**
     * Les filtres de l'écran, hors onglet de statut.
     *
     * Partagée par la liste et par « sélectionner tous les résultats » : les deux
     * doivent désigner le même ensemble, et deux copies l'ont déjà démenti.
     *
     * La recherche est enfermée dans son propre groupe parce que `when()` n'ouvre
     * aucune parenthèse et que `AND` lie plus fort que `OR` : à plat, la branche
     * « nom du membre » s'évade du filtre de statut et un paiement soldé remonte
     * dans l'onglet « À rembourser ».
     *
     * @param  Builder<Payment>  $q
     * @return Builder<Payment>
     */
    private function applyFilters(Builder $q): Builder
    {
        return $q
            ->when($this->search, fn (Builder $q): Builder => $q->where(function (Builder $q): void {
                $q->where('reference', 'like', "%{$this->search}%")
                    // `payable.user` suppose que tout payable a un membre. Une commande
                    // de bar n'en a pas — le bar ne sait pas qui a payé — et la
                    // recherche tombait alors en BadMethodCallException pour tout le
                    // monde, y compris pour chercher une affiliation.
                    ->orWhereHasMorph(
                        'payable',
                        $this->payableTypesWithUser(),
                        fn ($q) => $q->whereHas('user', fn ($u) => $u
                            ->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                        )
                    );
            }))
            ->when($this->paymentMethod, fn (Builder $q): Builder => $q->where('payment_method', $this->paymentMethod))
            ->when($this->dateFrom, fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $this->dateTo))
            ->when($this->userId, fn (Builder $q): Builder => $q->whereHasMorph(
                'payable',
                [Subscription::class, TournamentRegistration::class, MeetingUser::class],
                fn ($q) => $q->where('user_id', $this->userId)
            ))
            ->when($this->eventType, fn (Builder $q): Builder => $q->where('payable_type', $this->eventType))
            ->when($this->eventName, fn (Builder $q): Builder => $this->applyEventNameFilter($q, $this->eventName));
    }

    private function applyTab(Builder $q): Builder
    {
        if ($this->statusFilter !== 'overpaid') {
            return $q->where('status', $this->statusFilter);
        }

        return $q->whereColumn('amount_paid', '>', 'amount_due')
            ->where(fn (Builder $q): Builder => $q
                ->where('payment_method', '!=', 'refund')
                ->orWhereNull('payment_method'));
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
     * Les lignes dont les crédits dépassent le dû.
     *
     * Une position, pas un statut : rien n'est stocké, et le filtre doit donc
     * vivre au même endroit pour la carte et pour l'onglet.
     *
     * @return Builder<Payment>
     */
    private function overpaid(): Builder
    {
        return Payment::whereColumn('amount_paid', '>', 'amount_due')
            ->where(fn (Builder $q): Builder => $q
                ->where('payment_method', '!=', 'refund')
                ->orWhereNull('payment_method'));
    }

    private function payableEagerLoads(): array
    {
        return [
            TournamentRegistration::class => ['user', 'tournament'],
            MeetingUser::class => ['user', 'meeting'],
            Subscription::class => ['user', 'season'],
        ];
    }

    /**
     * @return array<class-string, array<int, string>>
     */
    /**
     * Les payables qui désignent un membre.
     *
     * Tous ne le font pas : une commande de bar n'a pas de payeur identifié, et
     * toute requête qui traverse `payable.user` sans borner les types tombe dès
     * qu'une telle ligne existe.
     *
     * @return list<class-string>
     */
    private function payableTypesWithUser(): array
    {
        return array_keys($this->payableEagerLoads());
    }

    /**
     * Le compte d'où vient l'argent en trop.
     *
     * Le dernier crédit adossé à une transaction entrante : c'est ce versement
     * qui a fait basculer la ligne en trop-perçu, et c'est là qu'il faut rendre.
     */
    private function payingAccountOf(Payment $payment): ?string
    {
        return $payment->credits()
            ->whereHas('transaction', fn (Builder $q): Builder => $q->where('amount', '>', 0))
            ->with('transaction')
            ->latest('id')
            ->first()?->transaction?->counterparty_bank_account;
    }

    /**
     * Comme {@see payableEagerLoads}, plus les tuteurs.
     *
     * Le barème de rapprochement interroge l'IBAN et le nom de chaque tuteur ;
     * la liste principale, elle, ne les affiche jamais et n'a pas à les payer.
     *
     * @return array<class-string, array<int, string>>
     */
    private function reconcileEagerLoads(): array
    {
        return [
            TournamentRegistration::class => ['user.guardians', 'tournament'],
            MeetingUser::class => ['user.guardians', 'meeting'],
            Subscription::class => ['user.guardians', 'season'],
        ];
    }

    /** @return Builder<Payment> */
    private function scopedToTab(): Builder
    {
        return $this->applyTab(Payment::query());
    }

    /**
     * Ce que l'onglet courant désigne.
     *
     * « Trop-perçus » n'est pas un statut : c'est une position, les crédits
     * dépassent le dû. Même raisonnement que pour « partiellement payé », qu'on
     * a refusé d'inventer comme statut — un état dérivé ne se stocke pas.
     *
     * @param  Builder<Payment>  $q
     * @return Builder<Payment>
     */
    /**
     * La colonne sur laquelle ranger, quand la colonne affichée n'est pas
     * celle qui est déclarée.
     *
     * L'en-tête « Montant » porte la clé `amount_due` sur les quatre onglets,
     * alors que la cellule montre le solde, l'encaissé ou l'excédent selon
     * l'onglet. Trier sur la clé déclarée rangeait sur un chiffre que personne
     * ne voit — invisible tant que rien n'est crédité en plusieurs fois, et
     * faux dès le premier acompte.
     */
    private function sortColumn(): string
    {
        if ($this->sortBy['column'] !== 'amount_due') {
            return $this->sortBy['column'];
        }

        return match ($this->statusFilter) {
            'overpaid' => 'overpayment',
            'paid' => 'amount_paid',
            default => 'balance',
        };
    }
};
