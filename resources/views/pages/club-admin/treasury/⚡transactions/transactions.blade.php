<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Bank Transactions')" :subtitle="__('Imported bank statements')" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                :placeholder="__('Search counterparty, reference...')"
                wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass"
                class="border-none bg-base-200 w-64" />
        </x-slot:middle>
        <x-slot:actions>
            <x-button
                :label="__('Import CSV')"
                icon="o-arrow-up-tray"
                class="btn-primary btn-sm"
                wire:click="$set('importModal', true)" />
        </x-slot:actions>
    </x-header>

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
                <x-icon name="o-clock" class="w-10 h-10 text-warning opacity-40" />
            </div>
        </x-card>
    </div>

    <x-card class="bg-base-100 border-none shadow-sm">
        <x-table :headers="$headers" :rows="$transactions" :sort-by="$sortBy" hover>

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

    {{-- Modal : Import --}}
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
</div>
