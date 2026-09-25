<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div x-data="{ mobileSearchOpen: false, mobileActionsOpen: false }">
    <x-header :title="__('Bank Transactions')" :subtitle="__('Imported bank statements')" separator progress-indicator>
        <x-slot:middle>
            <div class="hidden w-full lg:block">
                <x-input class="w-full" clearable icon="o-magnifying-glass"
                    :placeholder="__('Search counterparty, reference...')"
                    wire:model.live.debounce.300ms="search" />
            </div>
        </x-slot:middle>
        <x-slot:actions>
            {{-- Mobile: 🔍 · filter · ☰ --}}
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" />
            {{-- Desktop: full buttons --}}
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
                @can('transactions.import')
                    <x-button
                        :label="__('Import CSV')"
                        icon="o-arrow-up-tray"
                        class="btn-primary btn-sm"
                        wire:click="$set('importModal', true)" />
                @endcan
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
                    placeholder="{{ __('Search counterparty, reference...') }}" />
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
            :label="__('Total')"
            :value="$this->stats['total']"
            :hint="__('transactions imported')"
            icon="o-building-library" />

        <x-admin.shared.stat-card
            :label="__('Settled')"
            :value="$this->stats['reconciled']"
            :hint="__('fully allocated or written off')"
            icon="o-check-badge"
            color="success" />

        <x-admin.shared.stat-card
            :label="__('Partly allocated')"
            :value="$this->stats['partial']"
            :hint="__('something still to place')"
            icon="o-adjustments-horizontal"
            color="info" />

        <x-admin.shared.stat-card
            :label="__('Unreconciled')"
            :value="$this->stats['unreconciled']"
            :hint="__('nothing allocated yet')"
            icon="o-clock"
            color="warning" />
    </div>

    <x-card class="bg-base-100 shadow-sm">
        {{-- Selecting only ever leads to deleting: a reader gets no checkboxes. --}}
        <x-table :headers="$headers" :rows="$transactions" :sort-by="$sortBy" wire:model.live="selected" :selectable="auth()->user()->can('transactions.delete')" hover>

            @scope('cell_date', $transaction)
            <span class="text-sm tabular-nums">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</span>
            @endscope

            @scope('cell_counterparty_name', $transaction)
            <div>
                <div class="font-medium text-sm">{{ $transaction->counterparty_name ?? '—' }}</div>
                @if($transaction->counterparty_bank_account)
                <div class="font-mono text-xs text-muted">{{ $transaction->counterparty_bank_account }}</div>
                @endif
            </div>
            @endscope

            @scope('cell_structured_reference', $transaction)
            @if($transaction->structured_reference)
            <span class="font-mono text-xs text-primary">{{ $transaction->structured_reference }}</span>
            @elseif($transaction->free_reference)
            <span class="text-xs opacity-60 italic truncate max-w-xs block">{{ $transaction->free_reference }}</span>
            @else
            <span class="opacity-30">—</span>
            @endif
            @endscope

            @scope('cell_amount', $transaction)
            <span @class([
                'tabular-nums font-bold',
                'text-success' => $transaction->amount > 0,
                'text-error'   => $transaction->amount < 0,
            ])>
                {{ number_format($transaction->amount, 2, ',', ' ') }} €
            </span>
            @endscope

            @scope('cell_status', $transaction)
            @if($transaction->isSettled())
            <x-badge value="{{ __('Settled') }}" class="badge-success badge-sm badge-soft" />
            @elseif($transaction->allocated_amount != 0)
            {{-- Ce qui reste à placer : c'est le seul chiffre qui dit au trésorier ce qu'il lui reste à faire sur cette ligne. --}}
            <x-badge value="{{ __(':amount € left', ['amount' => number_format(abs($transaction->residue()), 2, ',', ' ')]) }}"
                class="badge-info badge-sm badge-soft" />
            @elseif($transaction->amount < 0)
            <x-badge value="{{ __('Outgoing') }}" class="badge-error badge-sm badge-soft" />
            @else
            <x-badge value="{{ __('Pending') }}" class="badge-warning badge-sm badge-soft" />
            @endif
            @endscope

            @scope('cell_allocate', $transaction)
            @can('payments.reconcile')
                @unless($transaction->isSettled())
                    {{-- Le geste naturel part du virement : une ligne peut solder
                         plusieurs paiements, et l'ouvrir depuis chaque fiche
                         obligerait à retrouver deux fois le même relevé. --}}
                    <x-button
                        :label="__('Allocate')"
                        icon="o-arrows-pointing-in"
                        wire:click="openAllocation({{ $transaction->id }})"
                        class="btn-xs btn-outline" />
                @endunless
            @endcan
            @endscope

        </x-table>

        @if($transactions->total() === 0)
        <div class="flex flex-col items-center justify-center py-12 text-muted">
            <x-icon name="o-building-library" class="w-12 h-12 mb-4" />
            <p class="text-sm italic">{{ __('No transactions yet. Import a bank statement to get started.') }}</p>
        </div>
        @endif

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    </x-card>


    {{-- ========================================== --}}
    {{-- Import history                              --}}
    {{-- ========================================== --}}
    @if($recentImports->isNotEmpty())
    <x-collapse class="mt-6 border border-base-300 rounded-xl bg-base-100">
        <x-slot:heading>
            <div class="flex items-center gap-2 text-sm font-semibold">
                <x-icon name="o-arrow-up-tray" class="w-4 h-4 opacity-50" />
                {{ __('Import history') }}
                <x-badge value="{{ $recentImports->count() }}" class="badge-ghost badge-sm" />
            </div>
        </x-slot:heading>
        <x-slot:content class="p-0">
            <table class="table table-sm w-full">
                <thead>
                    <tr class="text-xs text-muted uppercase tracking-widest">
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('By') }}</th>
                        <th class="text-right text-success">{{ __('New') }}</th>
                        <th class="text-right text-base-content/40">{{ __('Duplicates') }}</th>
                        <th class="text-right text-error">{{ __('Errors') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentImports as $import)
                    <tr @class(['hover', 'text-error/60' => $import->error_count > 0])>
                        <td class="tabular-nums text-xs">{{ $import->created_at->format('d/m/Y H:i') }}</td>
                        <td class="text-xs">{{ $import->user->name ?? '—' }}</td>
                        <td class="text-right font-bold text-success">+{{ $import->new_count }}</td>
                        <td class="text-right text-muted">{{ $import->duplicate_count }}</td>
                        <td class="text-right">
                            @if($import->error_count > 0)
                            <span class="text-error font-semibold">{{ $import->error_count }}</span>
                            @else
                            <span class="opacity-30">0</span>
                            @endif
                        </td>
                    </tr>
                    @if($import->error_count > 0 && $import->failed_rows)
                    <tr>
                        <td colspan="5" class="bg-error/5 text-xs p-3">
                            <div class="font-semibold text-error mb-1">{{ __('Failed rows:') }}</div>
                            @foreach($import->failed_rows as $failed)
                            <div class="opacity-70">
                                {{ __('Line :n', ['n' => $failed['line']]) }} — {{ $failed['reason'] }}
                            </div>
                            @endforeach
                        </td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </x-slot:content>
    </x-collapse>
    @endif

    {{-- ========================================== --}}
    {{-- Floating selection pill                     --}}
    {{-- ========================================== --}}
    <x-admin.shared.selection-pill
        :selected="$selected"
        :total="$this->getTotalMatchingCount()"
        :selecting-all-results="$selectingAllResults"
        :select-all="$selectAll">
        <x-slot:actions>
            <x-button
                wire:click="openConfirmDeleteModal"
                icon="o-trash"
                :label="__('Delete')"
                class="btn-ghost btn-sm text-error" />
        </x-slot:actions>
    </x-admin.shared.selection-pill>


    {{-- ========================================== --}}
    {{-- Modal : Confirm bulk delete                 --}}
    {{-- ========================================== --}}
    <x-confirm-modal
        model="confirmDeleteModal"
        :title="__('Delete transactions')"
        :confirm-label="__('Delete')"
        confirmClass="btn-error"
        confirmAction="bulkDelete" :open="$confirmDeleteModal">
        <div class="space-y-3">
            <p class="text-sm">
                {{ trans_choice(
                    '{1} Delete :count transaction?|[2,*] Delete :count transactions?',
                    $selectingAllResults ? $this->getTotalMatchingCount() : count($selected),
                    ['count' => $selectingAllResults ? $this->getTotalMatchingCount() : count($selected)]
                ) }}
            </p>
            @if ($reconciledInSelection > 0)
            <div class="flex items-start gap-2 rounded-lg border border-warning/30 bg-warning/10 p-3 text-sm">
                <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-warning-content shrink-0 mt-0.5" />
                <span>
                    {{ trans_choice(
                        '{1} :count of the selected transactions is already reconciled with a payment and will be unlinked.|[2,*] :count of the selected transactions are already reconciled with payments and will be unlinked.',
                        $reconciledInSelection,
                        ['count' => $reconciledInSelection]
                    ) }}
                </span>
            </div>
            @endif
        </div>
    </x-confirm-modal>


    {{-- ========================================== --}}
    {{-- Filter drawer                              --}}
    {{-- ========================================== --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <x-input
                :label="__('From')"
                wire:model.live="dateFrom"
                type="date" />

            <x-input
                :label="__('To')"
                wire:model.live="dateTo"
                type="date" />

            <x-select
                :label="__('Reconciliation')"
                wire:model.live="reconciledFilter"
                :options="$reconciledOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All')"
                clearable />

            <x-select
                :label="__('Direction')"
                wire:model.live="amountDirection"
                :options="$amountDirectionOptions"
                option-value="id"
                option-label="name"
                :placeholder="__('All')"
                clearable />
        </x-slot:filters>
    </x-admin.shared.filter-drawer>


    {{-- ========================================== --}}
    {{-- Modal : Import                             --}}
    {{-- ========================================== --}}
    <x-app-modal wire:model="importModal" :title="__('Import Bank Statement')" separator :open="$importModal">
        <div class="space-y-4">
            <p class="text-sm opacity-70">
                {{ __('Upload your bank export (ODS, XLSX, CSV). Transactions will be imported and available for reconciliation.') }}
            </p>
            <p class="text-xs text-muted">
                {{ __('Expected columns: Date, Montant, Description, Nom contrepartie, Numéro de compte contrepartie, Communication structurée, Communication libre') }}
            </p>
            <x-file
                wire:model="importFile"
                :label="__('Bank file')"
                :aria-label="__('Bank file')"
                accept=".ods,.xlsx,.xls,.csv,.txt"
                hint="ODS · XLSX · CSV" />
        </div>

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.importModal = false" class="btn-ghost" />
            <x-button
                :label="__('Start Import')"
                icon="o-arrow-up-tray"
                class="btn-primary"
                wire:click="processImport"
                :disabled="! $importFile"
                spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- ── Mobile action sheet ─────────────────────────────────────────── --}}
    <x-admin.shared.mobile-actions>
        <x-admin.shared.mobile-action-item
            icon="o-arrow-up-tray" color="primary"
            :label="__('Import CSV')"
            :description="__('Upload a bank statement')"
            @click="mobileActionsOpen = false; $wire.set('importModal', true)" />
        <div class="my-1 h-px bg-base-200"></div>
        <x-admin.shared.mobile-action-item
            icon="o-check-circle" color="base"
            :label="__('Select')"
            :description="__('Bulk actions on multiple transactions')"
            @click="mobileActionsOpen = false; $wire.call('toggleSelectionMode')" />
    </x-admin.shared.mobile-actions>

    {{-- Affecter une ligne de relevé --}}
    <x-app-modal wire:model="allocationModal" :title="__('Allocate this transaction')" separator box-class="max-w-2xl"
        :open="$allocationModal">
        @if($this->allocationTransaction)
            @php
                $tx = $this->allocationTransaction;
            @endphp
            <div class="space-y-4">
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-lg border border-base-300 bg-base-200/60 p-3">
                        {{-- Le sens de l'argent, pas sa valeur absolue : « Reçu »
                             sur un virement sortant était un contresens. --}}
                        <div class="text-xs uppercase tracking-widest text-muted">
                            {{ $tx->amount < 0 ? __('Paid out') : __('Received') }}
                        </div>
                        <div class="font-black tabular-nums">{{ number_format(abs($tx->amount), 2, ',', ' ') }} €</div>
                    </div>
                    <div class="rounded-lg border border-base-300 bg-base-200/60 p-3">
                        <div class="text-xs uppercase tracking-widest text-muted">{{ __('Allocated') }}</div>
                        <div class="font-black tabular-nums">{{ number_format(abs($tx->allocated_amount), 2, ',', ' ') }} €</div>
                    </div>
                    <div class="rounded-lg border border-info/20 bg-info/10 p-3">
                        <div class="text-xs uppercase tracking-widest text-muted">{{ __('Left to place') }}</div>
                        <div class="font-black tabular-nums text-info">{{ number_format($this->remainingToAllocate, 2, ',', ' ') }} €</div>
                    </div>
                </div>

                {{-- Qui a payé, et avec quelle communication. C'est la première
                     chose dont on a besoin pour décider, et elle manquait. --}}
                <div class="rounded-lg border border-base-300 bg-base-200/60 p-3 text-sm">
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        <span class="font-semibold">{{ $tx->counterparty_name ?: __('Unknown counterparty') }}</span>
                        <span class="text-xs opacity-60">{{ $tx->date?->format('d/m/Y') }}</span>
                    </div>
                    @if ($tx->structured_reference || $tx->free_reference)
                        <div class="mt-0.5 font-mono text-xs text-primary">
                            {{ $tx->structured_reference ?: $tx->free_reference }}
                        </div>
                    @endif
                    @if ($tx->counterparty_bank_account)
                        <div class="mt-0.5 font-mono text-xs opacity-50">{{ $tx->counterparty_bank_account }}</div>
                    @endif
                </div>

                <x-input :placeholder="__('Search a member or a reference...')"
                    wire:model.live.debounce.300ms="allocationSearch"
                    icon="o-magnifying-glass" clearable />

                @php
                    // Ce que le barème reconnaît, et le reste. Les mélanger
                    // présente vingt noms sans raison comme des suggestions,
                    // ce qui est un contresens : ce ne sont que les créances
                    // ouvertes du club.
                    [$suggested, $others] = $this->allocationCandidates
                        ->partition(fn ($c) => ($c->match?->strength->rank() ?? 0) > 0);
                @endphp

                @if ($suggested->isNotEmpty())
                    <div>
                        <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-success">
                            {{ __('Suggested (:count)', ['count' => $suggested->count()]) }}
                        </h3>
                        @php
                            $rows = $suggested;
                        @endphp
                        <div class="space-y-2">
                        @foreach ($rows as $candidate)
                            <div class="flex items-center gap-3 rounded-lg border p-3 {{ $candidate->match?->strength->rank() > 0 ? 'border-success/30 bg-success/5' : 'border-base-300' }}"
                                wire:key="alloc-{{ $candidate->id }}">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">
                                        {{ $candidate->payable instanceof \App\Contracts\DescribesPayment ? $candidate->payable->getPayerName() : '—' }}
                                    </div>
                                    <div class="font-mono text-xs text-primary">{{ $candidate->reference }}</div>

                                    {{-- Pourquoi ce candidat est proposé. Sans cette
                                         raison, une liste triée ressemble à une liste
                                         au hasard. --}}
                                    @if ($candidate->match && $candidate->match->reasons !== [])
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($candidate->match->reasons as $reason)
                                                <span class="badge badge-success badge-soft badge-xs">{{ $reason }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 text-right">
                                    <div class="whitespace-nowrap text-xs opacity-60">
                                        {{ __('owes :amount €', ['amount' => number_format($candidate->amount_due - $candidate->amount_paid, 2, ',', ' ')]) }}
                                    </div>
                                    <x-button
                                        :label="__('Allocate :amount €', ['amount' => number_format(min($candidate->amount_due - $candidate->amount_paid, max(0, $this->remainingToAllocate)), 2, ',', ' ')])"
                                        wire:click="suggestAllocation({{ $candidate->id }})"
                                        class="btn-xs btn-ghost mt-1" />
                                </div>

                                <x-input type="number" step="0.01" min="0" class="w-28"
                                    wire:model.live.blur="allocations.{{ $candidate->id }}" />
                            </div>
                        @endforeach
                        </div>
                    </div>
                @elseif ($this->allocationCandidates->isNotEmpty())
                    <div class="flex items-start gap-3 rounded-lg border border-warning/20 bg-warning/5 p-3 text-sm">
                        <x-icon name="o-question-mark-circle" class="mt-0.5 h-4 w-4 shrink-0 text-warning" />
                        <div>
                            <div class="font-semibold">{{ __('No payment matches this transfer') }}</div>
                            <p class="mt-1 text-xs opacity-80">
                                {{ __('Neither the communication nor the counterparty points to a member. Search below if you know who it is, write off what is left if the club keeps it, or leave the line unreconciled.') }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($others->isNotEmpty())
                    <div>
                        <h3 class="mb-2 text-xs font-bold uppercase tracking-widest text-muted">
                            {{ __('All open claims (:count)', ['count' => $others->count()]) }}
                        </h3>
                        @php
                            $rows = $others;
                        @endphp
                        <div class="max-h-72 space-y-2 overflow-y-auto">
                        @foreach ($rows as $candidate)
                            <div class="flex items-center gap-3 rounded-lg border p-3 {{ $candidate->match?->strength->rank() > 0 ? 'border-success/30 bg-success/5' : 'border-base-300' }}"
                                wire:key="alloc-{{ $candidate->id }}">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold">
                                        {{ $candidate->payable instanceof \App\Contracts\DescribesPayment ? $candidate->payable->getPayerName() : '—' }}
                                    </div>
                                    <div class="font-mono text-xs text-primary">{{ $candidate->reference }}</div>

                                    {{-- Pourquoi ce candidat est proposé. Sans cette
                                         raison, une liste triée ressemble à une liste
                                         au hasard. --}}
                                    @if ($candidate->match && $candidate->match->reasons !== [])
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($candidate->match->reasons as $reason)
                                                <span class="badge badge-success badge-soft badge-xs">{{ $reason }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="shrink-0 text-right">
                                    <div class="whitespace-nowrap text-xs opacity-60">
                                        {{ __('owes :amount €', ['amount' => number_format($candidate->amount_due - $candidate->amount_paid, 2, ',', ' ')]) }}
                                    </div>
                                    <x-button
                                        :label="__('Allocate :amount €', ['amount' => number_format(min($candidate->amount_due - $candidate->amount_paid, max(0, $this->remainingToAllocate)), 2, ',', ' ')])"
                                        wire:click="suggestAllocation({{ $candidate->id }})"
                                        class="btn-xs btn-ghost mt-1" />
                                </div>

                                <x-input type="number" step="0.01" min="0" class="w-28"
                                    wire:model.live.blur="allocations.{{ $candidate->id }}" />
                            </div>
                        @endforeach
                        </div>
                    </div>
                @endif

                @if ($this->allocationCandidates->isEmpty())
                    <p class="py-6 text-center text-sm text-muted">
                        {{ $tx->amount < 0
                            ? __('No refund is waiting to be paid out. This transfer went somewhere else — write off what is left, or leave it unreconciled.')
                            : __('No payment is waiting for money. This transfer may be a subsidy, a sponsor or a supplier — write off what is left, or leave it unreconciled.') }}
                    </p>
                @endif

                @if(abs($tx->residue()) > 0.001)
                    <div class="space-y-3 rounded-xl border border-warning/20 bg-warning/5 p-3">
                        <p class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Write off what is left') }}</p>
                        <x-input :label="__('Reason')" wire:model.blur="residueReason"
                            :placeholder="__('Member rounded up, kept by the club')"
                            :hint="__('Mandatory. The club keeps what is left and the transaction drops off the list to handle.')" />
                        <x-button :label="__('Write off the residue')" icon="o-archive-box-x-mark"
                            wire:click="settleResidue" spinner="settleResidue" class="btn-sm btn-warning btn-outline" />
                    </div>
                @endif
            </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.allocationModal = false" class="btn-ghost" />
            <x-button :label="__('Allocate')" icon="o-arrows-pointing-in" class="btn-primary"
                wire:click="confirmAllocation" spinner="confirmAllocation" />
        </x-slot:actions>
    </x-app-modal>
</div>
