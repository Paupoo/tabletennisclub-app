<div>
    <x-header title="{{ __('Treasury') }}" subtitle="{{ __('Payment tracking') }}" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input
                placeholder="{{ __('Search ref. or name...') }}"
                wire:model.live.debounce.300ms="search"
                icon="o-magnifying-glass"
                class="border-none bg-base-200 w-64" />
        </x-slot:middle>
        <x-slot:actions>
            {{-- Bouton Import CSV --}}
            <x-button
                label="{{ __('Import CSV') }}"
                icon="o-arrow-up-tray"
                class="btn-outline btn-sm"
                wire:click="$set('importModal', true)" />

            <x-button
                label="{{ __('Export') }}"
                icon="o-arrow-down-tray"
                class="btn-ghost btn-sm" />
        </x-slot:actions>
    </x-header>

    {{-- Utilisation des Tabs pour le filtrage par statut --}}
    <x-tabs wire:model.live="statusFilter">
        <x-tab name="pending" label="{{ __('Pending') }}" icon="o-clock" />
        <x-tab name="paid" label="{{ __('Paid') }}" icon="o-check-badge" />
    </x-tabs>

    <x-card class="bg-base-100 border-none shadow-sm rounded-t-none">
        <x-table :headers="$headers" :rows="$payments" hover>

            @scope('cell_comm', $payment)
            <span class="font-mono text-sm tracking-tight text-primary">{{ $payment->comm }}</span>
            @endscope

            @scope('cell_family', $payment)
            <span class="font-medium tracking-wide">{{ __('Family') }} {{ $payment->family }}</span>
            @endscope

            @scope('cell_amount', $payment)
            <span class="tabular-nums font-bold text-base">€{{ number_format($payment->amount, 2, ',', '.') }}</span>
            @endscope

            @scope('cell_date', $payment)
            <span class="text-xs opacity-60">{{ \Carbon\Carbon::parse($payment->date)->format('m/d/Y') }}</span>
            @endscope

            @scope('actions', $payment)
            @if($this->statusFilter === 'pending')
            <x-button
                icon="o-check"
                label="{{ __('Reconcile') }}"
                wire:click="markAsPaid({{ $payment->id }})"
                class="btn-xs btn-ghost text-gray-400 hover:text-green-600" />
            @else
            <div class="flex items-center gap-2 text-green-500 text-xs font-bold">
                <x-icon name="o-check-circle" class="w-5 h-5" />
                {{ __('Paid') }}
            </div>
            @endif
            @endscope

        </x-table>

        {{-- Empty state --}}
        @if($payments->isEmpty())
        <div class="flex flex-col items-center justify-center py-12 text-gray-400">
            <x-icon name="o-banknotes" class="w-12 h-12 opacity-20 mb-4" />
            <p class="text-sm italic">{{ __('No payments to display in this category.') }}</p>
        </div>
        @endif
    </x-card>

    {{-- Modal optionnelle pour l'import CSV --}}
    <x-modal wire:model="importModal" title="{{ __('Import Bank Statements') }}" separator>
        <div class="space-y-4">
            <p class="text-sm opacity-70">
                {{ __('Upload your bank CSV export to automatically match references with pending registrations.') }}
            </p>
            <x-file wire:model="csvFile" label="{{ __('CSV File') }}" accept="text/csv" />
        </div>
        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" @click="$wire.importModal = false" />
            <x-button label="{{ __('Start Import') }}" class="btn-primary" icon="o-play" wire:click="processImport" />
        </x-slot:actions>
    </x-modal>
</div>