<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-header :title="__('Treasury')" :subtitle="__('Payment tracking')" separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search ref. or name...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @canany(['payments.reconcile', 'payments.refund', 'transactions.view'])
                <x-dropdown :label="__('More actions')" icon="o-ellipsis-vertical" right class="btn-ghost btn-sm">
                    @if($statusFilter === 'to_refund')
                        @can('payments.refund')
                            <x-menu-item icon="o-sparkles" :title="__('Auto-match refunds')"
                                wire:click="previewBatchRefundMatch" spinner="previewBatchRefundMatch" />
                        @endcan
                    @else
                        @can('payments.reconcile')
                            <x-menu-item icon="o-sparkles" :title="__('Auto-match')"
                                wire:click="previewBatchMatch" spinner="previewBatchMatch" />
                        @endcan
                    @endif
                    @can('transactions.view')
                    <x-menu-item icon="o-arrow-up-tray" :title="__('Import a bank statement')"
                        link="{{ route('admin.treasury.transactions') }}" />
                    @endcan
                </x-dropdown>
                @endcanany
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-200 lg:hidden" x-show="mobileSearchOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="display:none">
        <div class="flex items-center gap-2 px-4 py-2.5">
            <div class="flex flex-1 items-center gap-2 rounded-xl bg-base-200 px-3 py-2">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 text-base-content/40" />
                <input wire:model.live.debounce.300ms="search"
                    class="flex-1 bg-transparent text-sm outline-none placeholder:text-base-content/40"
                    placeholder="{{ __('Search ref. or name...') }}" />
            </div>
            <button type="button" @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm"
                aria-label="{{ __('Close the search') }}">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- Active filter chips --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-3">
        <x-admin.shared.stat-card
            :label="__('Pending')"
            :value="number_format($this->stats['pending_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['pending_count'] . ' ' . __('payment(s) awaiting reconciliation')"
            icon="o-clock"
            color="warning"
            class="{{ $statusFilter === 'pending' ? 'ring-2 ring-primary/30' : '' }}" />

        <x-admin.shared.stat-card
            :label="__('Paid')"
            :value="number_format($this->stats['paid_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['paid_count'] . ' ' . __('payment(s) received')"
            icon="o-check-badge"
            color="success"
            class="{{ $statusFilter === 'paid' ? 'ring-2 ring-primary/30' : '' }}" />

        <x-admin.shared.stat-card
            :label="__('To refund')"
            :value="number_format($this->stats['to_refund_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['to_refund_count'] . ' ' . __('refund(s) pending')"
            icon="o-arrow-uturn-left"
            :color="$this->stats['to_refund_count'] > 0 ? 'error' : 'neutral'"
            class="col-span-2 lg:col-span-1 {{ $statusFilter === 'to_refund' ? 'ring-2 ring-primary/30' : '' }}" />
    </div>

    {{-- Status filter — folder tabs; the filtered table lives outside as its own card --}}
    <x-admin.shared.tabs wire:model.live="statusFilter">
        <x-admin.shared.tab name="pending"   :label="__('Pending')"   icon="o-clock" />
        <x-admin.shared.tab name="paid"      :label="__('Paid')"      icon="o-check-badge" />
        <x-admin.shared.tab name="to_refund" :label="__('To refund')" icon="o-arrow-uturn-left" />
    </x-admin.shared.tabs>

    {{-- ── Vue mobile ─────────────────────────────────────────────────
    The table is 724px wide and only scrolls sideways, which puts the row actions
    688px off the right edge of a phone — reachable only by a drag nobody guesses.
    Below lg the same rows are cards, as the members list already does. --}}
    <div class="grid grid-cols-1 gap-3 lg:hidden" data-mobile-list>
        @forelse ($payments as $payment)
            <div class="rounded-lg border border-base-300 bg-base-100 p-3" wire:key="mobile-payment-{{ $payment->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $payment->member }}</div>
                        @if ($payment->event_name)
                            <div class="truncate text-xs text-muted">
                                <span class="font-medium">{{ $payment->event_type }}</span> · {{ $payment->event_name }}
                            </div>
                        @endif
                    </div>
                    <div class="shrink-0 text-right font-bold tabular-nums">
                        @if ($this->statusFilter === 'paid')
                            {{ number_format($payment->amount_paid, 2, ',', ' ') }} €
                        @else
                            {{ number_format($payment->amount_due, 2, ',', ' ') }} €
                        @endif
                    </div>
                </div>

                <div class="mt-1 flex flex-wrap items-baseline gap-x-2 text-xs text-muted">
                    <span class="font-mono">{{ $payment->reference }}</span>
                    <span>·</span>
                    <span>{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y') }}</span>
                    @if ($payment->last_reminded_at)
                        @php $chasedDaysAgo = \Carbon\Carbon::parse($payment->last_reminded_at)->diffInDays(now()); @endphp
                        <span @class(['text-warning font-semibold' => $chasedDaysAgo >= 15])>
                            · {{ __('Chased :ago', ['ago' => \Carbon\Carbon::parse($payment->last_reminded_at)->diffForHumans()]) }}
                        </span>
                    @endif
                </div>

                <div class="mt-3">
                    @if ($this->statusFilter === 'pending')
                        @can('payments.reconcile')
                            <x-admin.shared.row-menu
                                :label="__('Reconcile')"
                                icon="o-link"
                                wire-click="openReconcile({{ $payment->id }})">
                                @can('payments.remind')
                                    <x-menu-item
                                        icon="o-paper-airplane"
                                        wire:click="sendReminder({{ $payment->id }})"
                                        :title="$payment->invitation_counter > 0
                                            ? __('Chase again (:n sent)', ['n' => $payment->invitation_counter])
                                            : __('Send invitation')" />
                                @endcan
                            </x-admin.shared.row-menu>
                        @endcan
                    @elseif ($this->statusFilter === 'to_refund')
                        @can('payments.refund')
                            <x-admin.shared.row-menu
                                :label="__('Reconcile')"
                                icon="o-link"
                                wire-click="openRefundReconcile({{ $payment->id }})" />
                        @endcan
                    @endif
                </div>
            </div>
        @empty
            <x-admin.shared.list-empty-state
                icon="o-banknotes"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No payments to display.')"
                :create-label="Gate::allows('transactions.view') ? __('Import a bank statement') : null"
                :create-href="Gate::allows('transactions.view') ? route('admin.treasury.transactions') : null" />
        @endforelse

        <div>{{ $payments->links() }}</div>
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <x-card class="hidden bg-base-100 shadow-sm rounded-t-none lg:block">
        <x-table :headers="$headers" :rows="$payments" :sort-by="$sortBy" wire:model.live="selected" selectable hover>

            @scope('cell_reference', $payment)
            <span class="font-mono text-sm tracking-tight text-primary">{{ $payment->reference }}</span>
            @endscope

            @scope('cell_member', $payment)
            <div>
                <span class="font-medium">{{ $payment->member }}</span>
                @if ($payment->event_name)
                    <div class="text-xs text-muted mt-0.5">
                        <span class="font-medium">{{ $payment->event_type }}</span>
                        · {{ $payment->event_name }}
                    </div>
                @endif
            </div>
            @endscope

            @scope('cell_amount_due', $payment)
            @if($this->statusFilter === 'paid')
            <span class="tabular-nums font-bold">{{ number_format($payment->amount_paid, 2, ',', ' ') }} €</span>
            @else
            <span class="tabular-nums font-bold">{{ number_format($payment->amount_due, 2, ',', ' ') }} €</span>
            @endif
            @endscope

            @scope('cell_created_at', $payment)
            {{-- The Date column already tells when. The reminder's age belongs on the
            same axis, and a payment never chased carries nothing: the absence is the
            information. The count lives on the button, so it is not repeated here. --}}
            <div class="text-xs">{{ \Carbon\Carbon::parse($payment->created_at)->format('d/m/Y') }}</div>
            @if ($payment->last_reminded_at)
                @php $chasedDaysAgo = \Carbon\Carbon::parse($payment->last_reminded_at)->diffInDays(now()); @endphp
                <div @class([
                    'text-xs mt-0.5',
                    'text-warning font-semibold' => $chasedDaysAgo >= 15,
                    'text-muted' => $chasedDaysAgo < 15,
                ])>
                    {{ __('Chased :ago', ['ago' => \Carbon\Carbon::parse($payment->last_reminded_at)->diffForHumans()]) }}
                </div>
            @endif
            @endscope

            @scope('cell_iban', $payment)
            @if($payment->iban)
                <span class="font-mono text-xs">{{ $payment->iban }}</span>
            @else
                <x-badge value="{{ __('Missing') }}" class="badge-warning badge-sm" icon="o-exclamation-triangle" />
            @endif
            @endscope

            @scope('actions', $payment)
            @if($this->statusFilter === 'pending')
            {{-- Reconcile is what the treasurer opens this tab to do, so it is the one
            action in the row. Chasing is named in the menu, count included — it used
            to live in a tooltip, which is nowhere at all under a thumb. --}}
            <x-admin.shared.row-menu
                :label="__('Reconcile')"
                icon="o-link"
                wire-click="openReconcile({{ $payment->id }})">
                @can('payments.remind')
                    <x-menu-item
                        icon="o-paper-airplane"
                        wire:click="sendReminder({{ $payment->id }})"
                        :title="$payment->invitation_counter > 0
                            ? __('Chase again (:n sent)', ['n' => $payment->invitation_counter])
                            : __('Send invitation')" />
                @endcan
            </x-admin.shared.row-menu>
            @elseif($this->statusFilter === 'to_refund')
            @can('payments.refund')
                <x-button
                    :label="__('Reconcile')"
                    icon="o-link"
                    wire:click="openRefundReconcile({{ $payment->id }})"
                    class="btn-xs btn-outline" />
            @endcan
            @else
            <div class="flex items-center gap-1.5 text-success text-xs font-bold">
                <x-icon name="o-check-circle" class="w-4 h-4" />
                {{ __('Paid') }}
            </div>
            @endif
            @endscope

        </x-table>

        @if($payments->total() === 0)
            {{-- A club opening its season lands here first. The shared component
            carries the action that fills the screen; the hand-rolled block it
            replaces stated the absence and stopped. --}}
            {{-- The action is offered only to whoever may actually take it: the
            read-only committee can read this screen but not the bank statements,
            and pointing them at a 403 is worse than offering nothing. --}}
            <x-empty-state
                icon="o-banknotes"
                :heading="__('No payments to display.')"
                :message="__('Cotisations appear here once a bank statement is imported or a payment is matched.')"
                :buttonText="Gate::allows('transactions.view') ? __('Import a bank statement') : null"
                :href="Gate::allows('transactions.view') ? route('admin.treasury.transactions') : null" />
        @endif

        <div class="mt-4">
            {{ $payments->links() }}
        </div>
    </x-card>


    {{-- ========================================== --}}
    {{-- Floating selection pill                     --}}
    {{-- ========================================== --}}
    <x-admin.shared.selection-pill
        :selected="$selected"
        :total="$this->getTotalMatchingCount()"
        :selecting-all-results="$selectingAllResults"
        :select-all="$selectAll">
        <x-slot:actions>
            @if ($statusFilter === 'pending')
            @can('payments.remind')
                <x-button
                    wire:click="openBulkReminderModal"
                    icon="o-paper-airplane"
                    :label="__('Send reminders')"
                    class="btn-ghost btn-sm" />
            @endcan
            @elseif ($statusFilter === 'to_refund')
            @can('payments.refund')
                <x-button
                    wire:click="openBulkCancelRefundModal"
                    icon="o-x-circle"
                    :label="__('Cancel refunds')"
                    class="btn-ghost btn-sm" />
            @endcan
            @endif
        </x-slot:actions>
    </x-admin.shared.selection-pill>


    {{-- ========================================== --}}
    {{-- Modal : Bulk reminder confirmation          --}}
    {{-- ========================================== --}}
    <x-confirm-modal
        model="bulkReminderModal"
        :title="__('Send reminders')"
        :subtitle="__('Queue a payment reminder for each selected member.')"
        :confirm-label="__('Send')"
        confirmClass="btn-primary"
        confirmAction="bulkSendReminder" :open="$bulkReminderModal">
        <p class="text-sm">
            {{ trans_choice('{1} Send :count reminder?|[2,*] Send :count reminders?', count($selected), ['count' => $selectingAllResults ? $this->getTotalMatchingCount() : count($selected)]) }}
        </p>
    </x-confirm-modal>


    {{-- ========================================== --}}
    {{-- Modal : Bulk cancel refund confirmation    --}}
    {{-- ========================================== --}}
    <x-confirm-modal
        model="bulkCancelRefundModal"
        :title="__('Cancel refunds')"
        :subtitle="__('Selected payments will be moved back to paid status. Payments already linked to a bank transaction will be skipped.')"
        :confirm-label="__('Confirm')"
        confirmClass="btn-warning"
        confirmAction="bulkCancelRefund" :open="$bulkCancelRefundModal">
        <p class="text-sm">
            {{ trans_choice('{1} Cancel :count refund?|[2,*] Cancel :count refunds?', count($selected), ['count' => $selectingAllResults ? $this->getTotalMatchingCount() : count($selected)]) }}
        </p>
    </x-confirm-modal>


    {{-- ========================================== --}}
    {{-- Filter drawer                              --}}
    {{-- ========================================== --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-select
                :label="__('Payment method')"
                wire:model.live="paymentMethod"
                :options="$paymentMethodOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All methods')"
                clearable />

            <x-input
                :label="__('From')"
                wire:model.live="dateFrom"
                type="date" />

            <x-input
                :label="__('To')"
                wire:model.live="dateTo"
                type="date" />

            <x-choices
                :label="__('Member')"
                wire:model.live="userId"
                :options="$usersSearchList"
                option-value="id"
                option-label="name"
                search-function="searchUsers"
                :no-result-text="__('No members found.')"
                debounce="300"
                min-chars="2"
                icon="o-magnifying-glass"
                single
                searchable
                clearable />

            <x-select
                :label="__('Event type')"
                wire:model.live="eventType"
                :options="$eventTypeOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All types')"
                clearable />

            <x-input
                :label="__('Event name')"
                wire:model.live.debounce.300ms="eventName"
                :placeholder="__('Search event name...')"
                icon="o-magnifying-glass"
                clearable />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>


    {{-- ========================================== --}}
    {{-- Modal : Réconciliation                     --}}
    {{-- ========================================== --}}
    <x-app-modal wire:model="reconcileModal" :title="__('Reconcile Payment')" separator box-class="max-w-2xl" :open="$reconcileModal">

        @if($currentPayment)

        <div class="flex items-center gap-4 p-4 rounded-xl bg-base-200/60 border border-base-300 mb-6">
            <x-icon name="o-document-text" class="w-8 h-8 text-primary shrink-0" />
            <div class="flex-1 min-w-0">
                @php $label = $currentPayment->payable instanceof \App\Contracts\DescribesPayment ? $currentPayment->payable->getPaymentLabel() : null; @endphp
                <div class="font-bold text-sm">{{ $currentPayment->payable instanceof \App\Contracts\DescribesPayment ? $currentPayment->payable->getPayerName() : '—' }}</div>
                @if ($label)
                    <div class="text-xs text-primary/70 mt-0.5">
                        <span class="font-medium">{{ $label['type'] }}</span> · {{ $label['name'] }}
                    </div>
                @endif
                <div class="font-mono text-xs text-primary mt-0.5">{{ $currentPayment->reference }}</div>
            </div>
            <div class="text-right shrink-0">
                <div class="text-lg font-black">{{ number_format($currentPayment->amount_due, 2, ',', ' ') }} €</div>
                <div class="text-xs text-muted">{{ __('expected') }}</div>
            </div>
        </div>

        <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            <div class="text-xs font-bold uppercase tracking-widest text-muted mb-3">
                {{ __('Unreconciled bank transactions') }}
            </div>

            @forelse($pendingTransactions as $transaction)
            @php
                $score = $transaction->match_score ?? 'none';
                $isPerfect = $score === 'perfect';
            @endphp
            <button type="button"
                wire:click="$set('selectedTransactionId', {{ $transaction->id }})"
                aria-pressed="{{ $selectedTransactionId === $transaction->id ? 'true' : 'false' }}"
                @class([
                    'w-full text-left flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-150',
                    'border-primary bg-primary/5 shadow-sm'   => $selectedTransactionId === $transaction->id,
                    'border-success/60 bg-success/5'          => $selectedTransactionId !== $transaction->id && $isPerfect,
                    'border-base-200 hover:border-base-300 bg-base-100' => $selectedTransactionId !== $transaction->id && !$isPerfect,
                ])>

                <div @class([
                    'w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center',
                    'border-primary bg-primary' => $selectedTransactionId === $transaction->id,
                    'border-base-300'           => $selectedTransactionId !== $transaction->id,
                ])>
                    @if($selectedTransactionId === $transaction->id)
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm truncate">{{ $transaction->counterparty_name ?? '—' }}</span>
                        @if($score === 'perfect')
                        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-success bg-success/15 px-1.5 py-0.5 rounded">{{ __('Perfect match') }}</span>
                        @elseif($score === 'reference')
                        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-info bg-info/15 px-1.5 py-0.5 rounded">{{ __('Ref. match') }}</span>
                        @elseif($score === 'amount')
                        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-warning-content bg-warning/15 px-1.5 py-0.5 rounded">{{ __('Amount match') }}</span>
                        @endif
                    </div>
                    @if($transaction->structured_reference)
                    <div class="font-mono text-xs text-primary mt-0.5">{{ $transaction->structured_reference }}</div>
                    @elseif($transaction->free_reference)
                    <div class="text-xs text-muted mt-0.5 truncate italic">{{ $transaction->free_reference }}</div>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    <div class="font-bold tabular-nums">{{ number_format($transaction->amount, 2, ',', ' ') }} €</div>
                    <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</div>
                </div>
            </button>

            @empty
            <div class="flex flex-col items-center justify-center py-10 text-muted">
                <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
                <p class="text-sm italic">{{ __('No unreconciled transactions. Import a bank statement first.') }}</p>
            </div>
            @endforelse
        </div>

        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.reconcileModal = false" class="btn-ghost" />
            <x-button
                :label="__('Confirm Reconciliation')"
                icon="o-check"
                class="btn-primary"
                wire:click="confirmReconcile"
                :disabled="! $selectedTransactionId"
                spinner />
        </x-slot:actions>
    </x-app-modal>


    {{-- ========================================== --}}
    {{-- Modal : Batch Auto-Réconciliation          --}}
    {{-- ========================================== --}}
    <x-app-modal wire:model="batchModal" :title="__('Auto-match — Confirm reconciliations')" separator box-class="max-w-2xl" :open="$batchModal">

        <div class="space-y-4">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-success/10 border border-success/20 text-sm">
                <x-icon name="o-sparkles" class="w-5 h-5 text-success shrink-0 mt-0.5" />
                <span>
                    {{ __(':count perfect match(es) found — structured reference and amount match exactly. Confirm to reconcile all at once.', ['count' => count($batchMatches)]) }}
                </span>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @foreach($batchMatches as $match)
                <div class="flex items-center gap-4 p-3 rounded-xl bg-base-100 border border-base-200">
                    <x-icon name="o-check-circle" class="w-5 h-5 text-success shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $match['member'] }}</div>
                        @if (! empty($match['event_name']))
                            <div class="text-xs text-muted mt-0.5">
                                <span class="font-medium">{{ $match['event_type'] }}</span> · {{ $match['event_name'] }}
                            </div>
                        @endif
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="font-mono text-xs text-primary">{{ $match['reference'] }}</span>
                            <span class="text-xs opacity-40">·</span>
                            <span class="text-xs opacity-60">{{ $match['counterparty'] }}</span>
                            <span class="text-xs opacity-40">·</span>
                            <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($match['transaction_date'])->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <span class="font-black tabular-nums text-success">{{ number_format($match['amount'], 2, ',', ' ') }} €</span>
                </div>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.batchModal = false" class="btn-ghost" />
            <x-button
                :label="__('Confirm all (:count)', ['count' => count($batchMatches)])"
                icon="o-check-badge"
                class="btn-success"
                wire:click="confirmBatchReconcile"
                spinner />
        </x-slot:actions>
    </x-app-modal>


    {{-- ========================================== --}}
    {{-- Modal : Réconciliation remboursement       --}}
    {{-- ========================================== --}}
    <x-app-modal wire:model="refundModal" :title="__('Confirm Refund')" separator box-class="max-w-2xl" :open="$refundModal">

        @if($currentRefundPayment)

        <div class="flex items-center gap-4 p-4 rounded-xl bg-error/5 border border-error/20 mb-6">
            <x-icon name="o-arrow-uturn-left" class="w-8 h-8 text-error shrink-0" />
            <div class="flex-1 min-w-0">
                @php $label = $currentRefundPayment->payable instanceof \App\Contracts\DescribesPayment ? $currentRefundPayment->payable->getPaymentLabel() : null; @endphp
                <div class="font-bold text-sm">{{ $currentRefundPayment->payable instanceof \App\Contracts\DescribesPayment ? $currentRefundPayment->payable->getPayerName() : '—' }}</div>
                @if ($label)
                    <div class="text-xs text-primary/70 mt-0.5">
                        <span class="font-medium">{{ $label['type'] }}</span> · {{ $label['name'] }}
                    </div>
                @endif
                <div class="font-mono text-xs text-primary mt-0.5">{{ $currentRefundPayment->reference }}</div>
                @if($currentRefundPayment->payable?->user?->iban)
                <div class="text-xs text-base-content/60 mt-0.5">IBAN : <span class="font-mono">{{ $currentRefundPayment->payable->user->iban }}</span></div>
                @endif
            </div>
            <div class="text-right shrink-0">
                <div class="text-lg font-black text-error">{{ number_format($currentRefundPayment->amount_paid, 2, ',', ' ') }} €</div>
                <div class="text-xs text-muted">{{ __('to refund') }}</div>
            </div>
        </div>

        <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
            <div class="text-xs font-bold uppercase tracking-widest text-muted mb-3">
                {{ __('Outgoing bank transactions') }}
            </div>

            @forelse($refundTransactions as $transaction)
            @php $score = $transaction->match_score ?? 'none'; @endphp
            <button type="button"
                wire:click="$set('selectedRefundTransactionId', {{ $transaction->id }})"
                aria-pressed="{{ $selectedRefundTransactionId === $transaction->id ? 'true' : 'false' }}"
                @class([
                    'w-full text-left flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-all duration-150',
                    'border-primary bg-primary/5 shadow-sm'                            => $selectedRefundTransactionId === $transaction->id,
                    'border-success/60 bg-success/5'                                   => $selectedRefundTransactionId !== $transaction->id && $score === 'perfect',
                    'border-info/40 bg-info/5'                                         => $selectedRefundTransactionId !== $transaction->id && $score === 'iban',
                    'border-warning/40 bg-warning/5'                                   => $selectedRefundTransactionId !== $transaction->id && $score === 'amount',
                    'border-base-200 hover:border-base-300 bg-base-100'               => $selectedRefundTransactionId !== $transaction->id && $score === 'none',
                ])>

                <div @class([
                    'w-4 h-4 rounded-full border-2 shrink-0 flex items-center justify-center',
                    'border-primary bg-primary' => $selectedRefundTransactionId === $transaction->id,
                    'border-base-300'           => $selectedRefundTransactionId !== $transaction->id,
                ])>
                    @if($selectedRefundTransactionId === $transaction->id)
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm truncate">{{ $transaction->counterparty_name ?? '—' }}</span>
                        @if($score === 'perfect')
                        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-success bg-success/15 px-1.5 py-0.5 rounded">{{ __('Perfect match') }}</span>
                        @elseif($score === 'iban')
                        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-info bg-info/15 px-1.5 py-0.5 rounded">{{ __('IBAN match') }}</span>
                        @elseif($score === 'amount')
                        <span class="shrink-0 text-xs font-bold uppercase tracking-wide text-warning-content bg-warning/15 px-1.5 py-0.5 rounded">{{ __('Amount match') }}</span>
                        @endif
                    </div>
                    @if($transaction->counterparty_bank_account)
                    <div class="font-mono text-xs text-base-content/50 mt-0.5">{{ $transaction->counterparty_bank_account }}</div>
                    @endif
                </div>

                <div class="text-right shrink-0">
                    <div class="font-bold tabular-nums text-error">{{ number_format($transaction->amount, 2, ',', ' ') }} €</div>
                    <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</div>
                </div>
            </button>

            @empty
            <div class="flex flex-col items-center justify-center py-10 text-muted">
                <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
                <p class="text-sm italic">{{ __('No outgoing transactions found. Import a bank statement containing the refund transfer.') }}</p>
            </div>
            @endforelse
        </div>

        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.refundModal = false" class="btn-ghost" />
            <x-button
                :label="__('Confirm Refund')"
                icon="o-arrow-uturn-left"
                class="btn-error"
                wire:click="confirmRefundReconcile"
                :disabled="! $selectedRefundTransactionId"
                spinner />
        </x-slot:actions>
    </x-app-modal>


    {{-- ========================================== --}}
    {{-- Modal : Batch remboursements               --}}
    {{-- ========================================== --}}
    <x-app-modal wire:model="refundBatchModal" :title="__('Auto-match refunds — Confirm')" separator box-class="max-w-2xl" :open="$refundBatchModal">

        <div class="space-y-4">
            <div class="flex items-start gap-3 p-3 rounded-xl bg-error/10 border border-error/20 text-sm">
                <x-icon name="o-sparkles" class="w-5 h-5 text-error shrink-0 mt-0.5" />
                <span>
                    {{ __(':count match(es) found — IBAN and amount match exactly. Confirm to mark all as refunded.', ['count' => count($refundBatchMatches)]) }}
                </span>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @foreach($refundBatchMatches as $match)
                <div class="flex items-center gap-4 p-3 rounded-xl bg-base-100 border border-base-200">
                    <x-icon name="o-arrow-uturn-left" class="w-5 h-5 text-error shrink-0" />
                    <div class="flex-1 min-w-0">
                        <div class="font-semibold text-sm">{{ $match['member'] }}</div>
                        @if (! empty($match['event_name']))
                            <div class="text-xs text-muted mt-0.5">
                                <span class="font-medium">{{ $match['event_type'] }}</span> · {{ $match['event_name'] }}
                            </div>
                        @endif
                        <div class="flex items-center gap-3 mt-0.5">
                            <span class="font-mono text-xs text-primary">{{ $match['reference'] }}</span>
                            @if($match['iban'])
                            <span class="text-xs opacity-40">·</span>
                            <span class="font-mono text-xs opacity-60">{{ $match['iban'] }}</span>
                            @endif
                            <span class="text-xs opacity-40">·</span>
                            <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($match['transaction_date'])->format('d/m/Y') }}</span>
                        </div>
                    </div>
                    <span class="font-black tabular-nums text-error">−{{ number_format($match['amount'], 2, ',', ' ') }} €</span>
                </div>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.refundBatchModal = false" class="btn-ghost" />
            <x-button
                :label="__('Confirm all (:count)', ['count' => count($refundBatchMatches)])"
                icon="o-arrow-uturn-left"
                class="btn-error"
                wire:click="confirmBatchRefundReconcile"
                spinner />
        </x-slot:actions>
    </x-app-modal>


    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        @if($statusFilter === 'to_refund')
            @can('payments.refund')
            <x-admin.shared.mobile-action-item
                icon="o-sparkles" color="error"
                :label="__('Auto-match refunds')"
                :description="__('Automatically match refund payments')"
                @click="mobileActionsOpen = false; $wire.call('previewBatchRefundMatch')" />
            @endcan
        @else
            @can('payments.reconcile')
            <x-admin.shared.mobile-action-item
                icon="o-sparkles" color="primary"
                :label="__('Auto-match')"
                :description="__('Automatically reconcile payments')"
                @click="mobileActionsOpen = false; $wire.call('previewBatchMatch')" />
            @endcan
        @endif
        @can('transactions.view')
        <x-admin.shared.mobile-action-item
            icon="o-arrow-up-tray" color="info"
            :label="__('Import a bank statement')"
            :description="__('Go to Transactions to import your bank export')"
            @click="mobileActionsOpen = false; window.location = '{{ route('admin.treasury.transactions') }}'" />
        @endcan
        <div class="my-1 h-px bg-base-200"></div>
        <x-admin.shared.mobile-action-item
            icon="o-check-circle" color="base"
            :label="__('Select')"
            :description="__('Bulk actions on multiple payments')"
            @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
    </x-admin.shared.mobile-actions>

</div>
