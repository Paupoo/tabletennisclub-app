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
                <x-button
                    :label="__('Import CSV')"
                    icon="o-arrow-up-tray"
                    class="btn-primary btn-sm"
                    wire:click="$set('importModal', true)" />
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
                    placeholder="{{ __('Search counterparty, reference...') }}" />
            </div>
            <button @click="mobileSearchOpen = false" class="btn btn-ghost btn-circle btn-sm">
                <x-icon name="o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </div>

    {{-- Active filter chips --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- Stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-card class="border border-base-200 bg-base-100" shadow>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-50">{{ __('Total') }}</div>
                    <div class="text-2xl font-black mt-1">{{ $this->stats['total'] }}</div>
                    <div class="text-xs opacity-60 mt-0.5">{{ __('transactions imported') }}</div>
                </div>
                <x-icon name="o-building-library" class="w-10 h-10 opacity-20" />
            </div>
        </x-card>

        <x-card class="border border-success/20 bg-success/5" shadow>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-50">{{ __('Reconciled') }}</div>
                    <div class="text-2xl font-black mt-1">{{ $this->stats['reconciled'] }}</div>
                    <div class="text-xs opacity-60 mt-0.5">{{ __('matched to a payment') }}</div>
                </div>
                <x-icon name="o-check-badge" class="w-10 h-10 text-success opacity-40" />
            </div>
        </x-card>

        <x-card class="border border-warning/20 bg-warning/5" shadow>
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-widest opacity-50">{{ __('Unreconciled') }}</div>
                    <div class="text-2xl font-black mt-1">{{ $this->stats['unreconciled'] }}</div>
                    <div class="text-xs opacity-60 mt-0.5">{{ __('incoming, no match yet') }}</div>
                </div>
                <x-icon name="o-clock" class="w-10 h-10 text-warning-content opacity-40" />
            </div>
        </x-card>
    </div>

    <x-card class="bg-base-100 border-none shadow-sm">
        <x-table :headers="$headers" :rows="$transactions" :sort-by="$sortBy" wire:model.live="selected" selectable hover>

            @scope('cell_date', $transaction)
            <span class="text-sm tabular-nums">{{ \Carbon\Carbon::parse($transaction->date)->format('d/m/Y') }}</span>
            @endscope

            @scope('cell_counterparty_name', $transaction)
            <div>
                <div class="font-medium text-sm">{{ $transaction->counterparty_name ?? '—' }}</div>
                @if($transaction->counterparty_bank_account)
                <div class="font-mono text-xs opacity-50">{{ $transaction->counterparty_bank_account }}</div>
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
            @if($transaction->payment)
            <x-badge value="{{ __('Reconciled') }}" class="badge-success badge-sm badge-soft" />
            @elseif($transaction->amount < 0)
            <x-badge value="{{ __('Outgoing') }}" class="badge-error badge-sm badge-soft" />
            @else
            <x-badge value="{{ __('Pending') }}" class="badge-warning badge-sm badge-soft" />
            @endif
            @endscope

        </x-table>

        @if($transactions->total() === 0)
        <div class="flex flex-col items-center justify-center py-12 opacity-40">
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
    <x-collapse class="mt-6 border border-base-200 rounded-xl bg-base-100">
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
                    <tr class="text-xs opacity-50 uppercase tracking-widest">
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
                        <td class="text-right opacity-40">{{ $import->duplicate_count }}</td>
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
        confirmAction="bulkDelete">
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
    <x-modal wire:model="importModal" :title="__('Import Bank Statement')" separator>
        <div class="space-y-4">
            <p class="text-sm opacity-70">
                {{ __('Upload your bank export (ODS, XLSX, CSV). Transactions will be imported and available for reconciliation.') }}
            </p>
            <p class="text-xs opacity-50">
                {{ __('Expected columns: Date, Montant, Description, Nom contrepartie, Numéro de compte contrepartie, Communication structurée, Communication libre') }}
            </p>
            <x-file
                wire:model="importFile"
                :label="__('Bank file')"
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
    </x-modal>

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
</div>
