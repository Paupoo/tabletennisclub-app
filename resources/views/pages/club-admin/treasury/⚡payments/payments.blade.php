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
                @canany(['payments.reconcile', 'payments.refund', 'transactions.import'])
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
                    @can('transactions.import')
                    <x-menu-item icon="o-arrow-up-tray" :title="__('Import a bank statement')"
                        link="{{ route('admin.treasury.transactions') }}" />
                    @endcan
                </x-dropdown>
                @endcanany
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Mobile search bar --}}
    <div class="border-b border-base-300 lg:hidden" x-show="mobileSearchOpen"
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
    <div class="grid grid-cols-1 gap-4 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.shared.stat-card
            :label="__('Pending')"
            :value="number_format($this->stats['pending_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['pending_count'] . ' ' . __('payment(s) awaiting reconciliation')"
            icon="o-clock"
            color="warning" />

        <x-admin.shared.stat-card
            :label="__('Paid')"
            :value="number_format($this->stats['paid_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['paid_count'] . ' ' . __('payment(s) received')"
            icon="o-check-badge"
            color="success" />

        <x-admin.shared.stat-card
            :label="__('To refund')"
            :value="number_format($this->stats['to_refund_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['to_refund_count'] . ' ' . __('refund(s) pending')"
            icon="o-arrow-uturn-left"
            :color="$this->stats['to_refund_count'] > 0 ? 'error' : 'neutral'" />

        {{-- Le quatrième onglet avait sa colonne dans le tableau mais aucun
             total : l'argent que le club détient en trop était le seul état
             qu'on ne pouvait pas lire d'un coup d'œil, alors que c'est celui
             qui appelle une action — il ne lui appartient plus. --}}
        <x-admin.shared.stat-card
            :label="__('Overpaid')"
            :value="number_format($this->stats['overpaid_total'], 2, ',', ' ') . ' €'"
            :hint="$this->stats['overpaid_count'] . ' ' . __('payment(s) held in excess')"
            icon="o-arrow-trending-up"
            :color="$this->stats['overpaid_count'] > 0 ? 'warning' : 'neutral'" />
    </div>

    {{-- Status filter — folder tabs; the filtered table lives outside as its own card --}}
    @if ($residueNotice)
        {{-- Ce que le rapprochement vient de laisser. Un toast s'efface ;
             cent euros à placer demandent qu'on y revienne. --}}
        <div class="mb-4 flex items-start gap-3 rounded-lg border border-info/20 bg-info/10 p-3 text-sm">
            <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-info" />
            <div class="flex-1">
                {{ __(':amount € are still to place on this transfer — allocate them elsewhere, write them off, or refund them.', [
                    'amount' => number_format($residueNotice['amount'], 2, ',', ' '),
                ]) }}
                @can('transactions.view')
                    <a href="{{ route('admin.treasury.transactions', ['allocate' => $residueNotice['transaction_id']]) }}"
                        wire:navigate class="link link-info ml-1">{{ __('Place it now') }}</a>
                @endcan
            </div>
            <x-button icon="o-x-mark" wire:click="$set('residueNotice', null)" class="btn-ghost btn-xs" />
        </div>
    @endif

    <x-admin.shared.tabs wire:model.live="statusFilter">
        <x-admin.shared.tab name="pending"   :label="__('Pending')"   icon="o-clock" />
        <x-admin.shared.tab name="paid"      :label="__('Paid')"      icon="o-check-badge" />
        <x-admin.shared.tab name="to_refund" :label="__('To refund')" icon="o-arrow-uturn-left" />
        {{-- Pas un statut : une position. Les crédits dépassent le dû, et cet
             argent n'appartient plus au club. --}}
        <x-admin.shared.tab name="overpaid" :label="__('Overpaid')" icon="o-arrow-trending-up" />
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
                    <div class="shrink-0 text-right tabular-nums">
                        @if ($this->statusFilter === 'overpaid')
                            {{-- Le net : ce que le club détient en trop, jamais
                                 « 220 sur 120 » qui a l'air d'un bug. --}}
                            <span class="font-bold text-warning">{{ number_format($payment->overpayment, 2, ',', ' ') }} €</span>
                            <div class="text-xs font-normal text-muted">{{ __('held by the club') }}</div>
                        @elseif ($this->statusFilter === 'paid')
                            <span class="font-bold">{{ number_format($payment->amount_paid, 2, ',', ' ') }} €</span>
                        @else
                            {{-- Le solde, pas le montant réclamé au départ : depuis
                                 qu'une ligne se crédite en plusieurs fois, les deux
                                 divergent, et le second réclame une somme reçue. --}}
                            <span class="font-bold">{{ number_format($payment->balance, 2, ',', ' ') }} €</span>
                            @if ($payment->is_partially_paid)
                                <div class="text-xs font-normal text-info">
                                    {{ __(':paid € received of :due €', [
                                        'paid' => number_format($payment->amount_paid, 2, ',', ' '),
                                        'due'  => number_format($payment->amount_due, 2, ',', ' '),
                                    ]) }}
                                </div>
                            @endif
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
                            {{-- Les deux gestes, comme sur le tableau : aller chercher
                                 de quoi virer, puis rapprocher quand le relevé arrive. --}}
                            <x-admin.shared.row-menu
                                :label="__('Transfer details')"
                                icon="o-clipboard-document"
                                wire-click="openRefundInstructions({{ $payment->id }})">
                                <x-menu-item
                                    icon="o-link"
                                    :title="__('Reconcile')"
                                    wire:click="openRefundReconcile({{ $payment->id }})" />
                            </x-admin.shared.row-menu>
                        @endcan
                    @endif
                </div>
            </div>
        @empty
            <x-admin.shared.list-empty-state
                icon="o-banknotes"
                :filtered="filled($search) || count($filterChips) > 0"
                :heading="__('No payments to display.')"
                :create-label="Gate::allows('transactions.import') ? __('Import a bank statement') : null"
                :create-href="Gate::allows('transactions.view') ? route('admin.treasury.transactions') : null" />
        @endforelse

        <div>{{ $payments->links() }}</div>
    </div>

    {{-- ── Vue desktop ────────────────────────────────────────────────── --}}
    <x-card class="hidden bg-base-100 shadow-sm rounded-t-none lg:block">
        {{-- The bulk actions are reminders and refunds: without either, nothing to select for. --}}
        <x-table container-class="overflow-x-auto lg:overflow-x-visible" :headers="$headers" :rows="$payments" :sort-by="$sortBy" wire:model.live="selected" :selectable="auth()->user()->canAny(['payments.remind', 'payments.refund'])" hover>

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
            @if($this->statusFilter === 'overpaid')
            <div class="tabular-nums">
                <span class="font-bold text-warning">{{ number_format($payment->overpayment, 2, ',', ' ') }} €</span>
                <div class="text-xs text-muted">{{ __('held by the club') }}</div>
            </div>
            @elseif($this->statusFilter === 'paid')
            <span class="tabular-nums font-bold">{{ number_format($payment->amount_paid, 2, ',', ' ') }} €</span>
            @else
            <div class="tabular-nums">
                <span class="font-bold">{{ number_format($payment->balance, 2, ',', ' ') }} €</span>
                @if ($payment->is_partially_paid)
                    <div class="text-xs text-info">
                        {{ __(':paid € received of :due €', [
                            'paid' => number_format($payment->amount_paid, 2, ',', ' '),
                            'due'  => number_format($payment->amount_due, 2, ',', ' '),
                        ]) }}
                    </div>
                @endif
            </div>
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
            {{-- Le compte à créditer, pas celui du membre : un trop-perçu se rend
                 d'où il vient, et afficher le titulaire quand un tiers a payé
                 donnerait à recopier le mauvais numéro. --}}
            @if($payment->refund_iban ?? $payment->iban)
                <span class="font-mono text-xs">{{ $payment->refund_iban ?? $payment->iban }}</span>
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
                {{-- Deux gestes, séparés de plusieurs semaines : on va chercher
                     de quoi faire le virement aujourd'hui, on le rapproche quand
                     le relevé arrive. La communication SEPA fait 140 caractères
                     et n'a qu'un usage — être copiée : elle vit donc dans la
                     modale, où elle tient en entier. --}}
                <x-admin.shared.row-menu
                    :label="__('Transfer details')"
                    icon="o-clipboard-document"
                    wire-click="openRefundInstructions({{ $payment->id }})">
                    <x-menu-item
                        icon="o-link"
                        :title="__('Reconcile')"
                        wire:click="openRefundReconcile({{ $payment->id }})" />
                </x-admin.shared.row-menu>
            @endcan
            @else
            <div class="flex items-center gap-1.5">
                <span class="flex items-center gap-1.5 text-success text-xs font-bold">
                    <x-icon name="o-check-circle" class="w-4 h-4" />
                    {{ __('Paid') }}
                </span>
                @can('payments.refund')
                    {{-- Le membre appelle, le trésorier ouvre. Ce geste n'existait
                         nulle part : un remboursement ne pouvait naître que d'un
                         changement de facture côté secrétariat. --}}
                    <x-button
                        :label="__('Refund')"
                        icon="o-arrow-uturn-left"
                        wire:click="openRefundRequest({{ $payment->id }})"
                        class="btn-xs btn-ghost" />
                @endcan
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
                :buttonText="Gate::allows('transactions.import') ? __('Import a bank statement') : null"
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
                <div class="text-lg font-black">{{ number_format($currentPayment->balance(), 2, ',', ' ') }} €</div>
                @if ($currentPayment->isPartiallyPaid())
                    <div class="text-xs text-info">
                        {{ __(':paid € received of :due €', [
                            'paid' => number_format($currentPayment->amount_paid, 2, ',', ' '),
                            'due'  => number_format($currentPayment->amount_due, 2, ',', ' '),
                        ]) }}
                    </div>
                @endif
                <div class="text-xs text-muted">{{ __('still expected') }}</div>
            </div>
        </div>

        @if ($currentPayment->credits->isNotEmpty())
            {{-- D'où vient ce qui est déjà là. Sans cette liste, le trésorier
                 lit « il reste 20 € » sans pouvoir vérifier les 100 autres :
                 il ne retient pas ses propres rapprochements. --}}
            <div class="mb-6">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-muted">{{ __('Already received') }}</h3>
                <div class="space-y-1.5">
                    @foreach ($currentPayment->credits as $credit)
                        <div class="flex items-center gap-3 rounded-lg border border-success/20 bg-success/5 p-2.5 text-sm"
                            wire:key="credit-{{ $credit->id }}">
                            <x-icon name="o-check-circle" class="h-4 w-4 shrink-0 text-success" />
                            <span class="flex-1 min-w-0 truncate">
                                @if ($credit->transaction)
                                    {{ \Illuminate\Support\Carbon::parse($credit->transaction->date)->format('d/m/Y') }}
                                    — {{ $credit->transaction->counterparty_name ?? __('Unknown counterparty') }}
                                @else
                                    {{ __('Received outside the bank') }}
                                @endif
                            </span>
                            <span class="shrink-0 font-bold tabular-nums text-success">
                                {{ number_format($credit->amount, 2, ',', ' ') }} €
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <x-admin.treasury.candidate-list
            :candidates="$pendingTransactions"
            :selected="$selectedTransactionId"
            property="selectedTransactionId"
            :heading="__('Unreconciled bank transactions')"
            :empty-message="__('No unreconciled transactions. Import a bank statement first.')" />

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
                    {{ __('Ticked lines are the ones where the reference and the amount match exactly. The others are defensible but need your eye.') }}
                </span>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto pr-1">
                @foreach($batchMatches as $key => $match)
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 {{ $match['exact'] ? 'border-success/30 bg-success/5' : 'border-warning/30 bg-warning/5' }}"
                        wire:key="batch-{{ $key }}">
                        <input type="checkbox" class="checkbox checkbox-sm mt-0.5"
                            value="{{ $key }}" wire:model.live="selectedBatchMatches" />

                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold">{{ $match['member'] }}</div>
                            @if (! empty($match['event_name']))
                                <div class="text-xs text-primary/70">
                                    <span class="font-medium">{{ $match['event_type'] }}</span> · {{ $match['event_name'] }}
                                </div>
                            @endif
                            <div class="mt-0.5 flex flex-wrap items-baseline gap-x-2">
                                <span class="font-mono text-xs text-primary">{{ $match['reference'] }}</span>
                                <span class="text-xs opacity-60">{{ $match['counterparty'] }}</span>
                                <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($match['transaction_date'])->format('d/m/Y') }}</span>
                            </div>
                            {{-- Pourquoi cette ligne est cochée, ou pourquoi elle ne l'est pas. --}}
                            <div class="mt-1 text-xs {{ $match['exact'] ? 'text-success' : 'text-warning' }}">
                                {{ $match['reason'] }}
                            </div>
                        </div>

                        <span class="shrink-0 font-black tabular-nums {{ $match['exact'] ? 'text-success' : 'text-warning' }}">
                            {{ number_format($match['amount'], 2, ',', ' ') }} €
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.batchModal = false" class="btn-ghost" />
            <x-button
                :label="__('Confirm the ticked (:count)', ['count' => count($selectedBatchMatches)])"
                icon="o-check-badge"
                class="btn-success"
                wire:click="confirmBatchReconcile"
                spinner />
        </x-slot:actions>
    </x-app-modal>


    {{-- ========================================== --}}
    {{-- Modal : de quoi faire le virement          --}}
    {{-- ========================================== --}}
    <x-app-modal wire:model="refundInstructionsModal" :title="__('Transfer details')" separator
        box-class="max-w-2xl" :open="$refundInstructionsModal">

        @if ($refundInstructions)
            <div class="flex items-start gap-3 rounded-lg border border-info/20 bg-info/10 p-3 text-sm">
                <x-icon name="o-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-info" />
                <span>{{ __('The transfer is made in your bank, not here. Copy the three lines below, then come back to reconcile it once the statement is imported.') }}</span>
            </div>

            <div class="mt-4 space-y-3">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Member') }}</p>
                    <p class="text-sm font-semibold">{{ $refundInstructions['member'] }}</p>
                    @if ($refundInstructions['event'])
                        <p class="text-xs text-muted">{{ $refundInstructions['event'] }}</p>
                    @endif
                </div>

                <x-admin.treasury.copyable-line
                    :label="__('Amount to transfer')"
                    :value="number_format($refundInstructions['amount'], 2, ',', ' ') . ' €'" />

                <x-admin.treasury.copyable-line
                    :label="__('Account to credit')"
                    :value="$refundInstructions['iban'] ?? __('No account on file — ask the member')"
                    :copyable="$refundInstructions['iban'] !== null"
                    mono />

                <x-admin.treasury.copyable-line
                    :label="__('Communication')"
                    :value="$refundInstructions['remittance'] ?? ''"
                    wrap />
            </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Close')" @click="$wire.refundInstructionsModal = false" class="btn-ghost" />
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
                {{-- Ce qu'il reste à virer, comme la ligne du tableau. `amount_paid`
                     compte ce qui est déjà sorti : zéro tant que le virement n'a
                     pas été fait, donc une modale d'exécution qui annonçait
                     0,00 € à rembourser. --}}
                <div class="text-lg font-black text-error">{{ number_format($currentRefundPayment->balance(), 2, ',', ' ') }} €</div>
                <div class="text-xs text-muted">{{ __('to refund') }}</div>
            </div>
        </div>

        <x-admin.treasury.candidate-list
            :candidates="$refundTransactions"
            :selected="$selectedRefundTransactionId"
            property="selectedRefundTransactionId"
            :heading="__('Outgoing bank transactions')"
            :empty-message="__('No outgoing transactions found. Import a bank statement containing the refund transfer.')"
            outgoing />

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
                <div class="flex items-center gap-4 p-3 rounded-xl bg-base-100 border border-base-300">
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
        @can('transactions.import')
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


    {{-- Ouvrir un remboursement --}}
    <x-app-modal wire:model="refundRequestModal" :title="__('Open a refund')" separator class="backdrop-blur-sm"
        :open="$refundRequestModal">
        <div class="space-y-4">
            <div class="flex items-center gap-3 rounded-lg border border-info/20 bg-info/10 p-3 text-sm">
                <x-icon name="o-information-circle" class="h-4 w-4 shrink-0 text-info" />
                <span>{{ __('The amount cannot exceed what the member has paid, minus refunds already opened.') }}</span>
            </div>

            <x-input :label="__('Amount (€)')" type="number" step="0.01" min="0"
                wire:model="refundRequestAmount" />

            <x-input :label="__('Account to refund')" wire:model="refundRequestIban"
                :hint="__('The account the money came from. A guardian or an employer pays for a member — the money goes back where it came from.')" />

            <x-input :label="__('Reason')" wire:model="refundRequestReason"
                :placeholder="__('Why is the club giving this money back?')"
                :hint="__('Mandatory. Sent to the treasury and kept with the payment.')" />
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.refundRequestModal = false" class="btn-ghost" />
            <x-button :label="__('Open the refund')" icon="o-arrow-uturn-left" class="btn-primary"
                wire:click="confirmRefundRequest" spinner="confirmRefundRequest" />
        </x-slot:actions>
    </x-app-modal>
</div>
