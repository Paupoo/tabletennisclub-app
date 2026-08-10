<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header :title="__('Cash Register')" :subtitle="__('In-person cash management')" separator progress-indicator>
        <x-slot:actions>
            <x-button
                :label="__('New register')"
                icon="o-building-library"
                class="btn-outline btn-sm"
                wire:click="$set('createRegisterModal', true)" />
            @if($this->register)
            <x-button
                :label="__('Add entry')"
                icon="o-plus"
                class="btn-primary btn-sm"
                wire:click="openManualEntry" />
            @endif
        </x-slot:actions>
    </x-header>

    @if($this->registers->isEmpty())
    <div class="flex flex-col items-center justify-center py-20 text-muted">
        <x-icon name="o-currency-euro" class="w-16 h-16 mb-4" />
        <p class="text-sm italic">{{ __('No cash register yet. Create one to get started.') }}</p>
    </div>
    @else

    {{-- Register selector (if multiple) --}}
    @if($this->registers->count() > 1)
    <div class="flex gap-2 mb-6">
        @foreach($this->registers as $reg)
        <x-button
            :label="$reg->name"
            wire:click="$set('selectedRegisterId', {{ $reg->id }})"
            @class(['btn-sm', 'btn-primary' => $selectedRegisterId === $reg->id, 'btn-outline' => $selectedRegisterId !== $reg->id]) />
        @endforeach
    </div>
    @endif

    @if($this->register)
    {{-- Holder info --}}
    <div class="flex items-center gap-3 mb-4 px-1">
        <x-icon name="o-user-circle" class="w-5 h-5 text-base-content/40 shrink-0" />
        <span class="text-sm text-base-content/60">{{ __('Holder') }}:</span>
        @if($this->register->heldBy)
            <span class="text-sm font-medium">{{ $this->register->heldBy->first_name }} {{ $this->register->heldBy->last_name }}</span>
        @else
            <span class="text-sm italic text-base-content/40">{{ __('None') }}</span>
        @endif
        @can('cash_register.holder.change')
            <x-button
                :label="__('Change')"
                icon="o-pencil"
                class="btn-ghost btn-xs ml-1"
                wire:click="openChangeHolder" />
        @endcan
    </div>

    {{-- Balance card --}}
    @php
        $entriesIn = $this->register->entries->where('amount', '>', 0);
        $entriesOut = $this->register->entries->where('amount', '<', 0);
    @endphp
    <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-3">
        <x-admin.shared.stat-card
            :label="__('Current balance')"
            :value="number_format($this->balance / 100, 2, ',', ' ') . ' €'"
            :hint="$this->register->name"
            icon="o-currency-euro"
            :color="$this->balance >= 0 ? 'success' : 'error'"
            emphasis
            class="col-span-2 lg:col-span-1" />

        <x-admin.shared.stat-card
            :label="__('Total in')"
            :value="number_format($entriesIn->sum('amount') / 100, 2, ',', ' ') . ' €'"
            :hint="$entriesIn->count() . ' ' . __('entries')"
            icon="o-arrow-down-tray"
            color="success" />

        <x-admin.shared.stat-card
            :label="__('Total out')"
            :value="number_format(abs($entriesOut->sum('amount')) / 100, 2, ',', ' ') . ' €'"
            :hint="$entriesOut->count() . ' ' . __('entries')"
            icon="o-arrow-up-tray"
            color="error" />
    </div>

    {{-- Entries history --}}
    <x-card class="bg-base-100 shadow-sm">
        <div class="text-xs font-bold uppercase tracking-widest text-muted mb-4">{{ __('History') }}</div>

        @forelse($this->register->entries->sortByDesc('created_at') as $entry)
        <div class="flex items-center gap-4 p-3 rounded-xl border border-base-300 mb-2">
            <div @class([
                'w-8 h-8 rounded-full flex items-center justify-center shrink-0',
                'bg-success/15' => $entry->amount > 0,
                'bg-error/15'   => $entry->amount < 0,
            ])>
                <x-icon
                    :name="$entry->amount > 0 ? 'o-arrow-down-tray' : 'o-arrow-up-tray'"
                    @class(['w-4 h-4', 'text-success' => $entry->amount > 0, 'text-error' => $entry->amount < 0]) />
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-sm">
                        {{ match($entry->reason) {
                            'tournament_payment' => __('Tournament payment'),
                            'training_payment'   => __('Training payment'),
                            default              => __('Manual entry'),
                        } }}
                    </span>
                    @if($entry->payable_type)
                    <x-badge value="{{ class_basename($entry->payable_type) }}" class="badge-ghost badge-sm" />
                    @endif
                </div>
                @if($entry->notes)
                <div class="text-xs opacity-60 mt-0.5 truncate">{{ $entry->notes }}</div>
                @endif
                <div class="text-xs text-muted mt-0.5">
                    {{ $entry->recordedBy?->first_name }} {{ $entry->recordedBy?->last_name }}
                    · {{ $entry->created_at->format('d/m/Y H:i') }}
                </div>
            </div>
            <div @class([
                'tabular-nums font-black text-right shrink-0',
                'text-success' => $entry->amount > 0,
                'text-error'   => $entry->amount < 0,
            ])>
                {{ $entry->amount > 0 ? '+' : '' }}{{ number_format($entry->amount / 100, 2, ',', ' ') }} €
            </div>
        </div>
        @empty
        <div class="flex flex-col items-center justify-center py-10 text-muted">
            <x-icon name="o-inbox" class="w-10 h-10 mb-3" />
            <p class="text-sm italic">{{ __('No entries yet.') }}</p>
        </div>
        @endforelse
    </x-card>
    @endif
    @endif

    {{-- Modal: Create register --}}
    <x-app-modal wire:model="createRegisterModal" :title="__('Create Cash Register')" separator :open="$createRegisterModal">
        <div class="space-y-4">
            <x-input :label="__('Register name')" wire:model="newRegisterName" autofocus />
            <x-select
                :label="__('Holder')"
                :options="$users"
                option-label="name"
                :placeholder="__('Select a holder...')"
                wire:model="newRegisterHolderUserId"
                clearable />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.createRegisterModal = false" class="btn-ghost" />
            <x-button :label="__('Create')" icon="o-check" class="btn-primary" wire:click="createRegister" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Modal: Change holder --}}
    <x-app-modal wire:model="changeHolderModal" :title="__('Change holder')" separator :open="$changeHolderModal">
        <x-select
            :label="__('Holder')"
            :options="$users"
            option-label="name"
            :placeholder="__('Select a holder...')"
            wire:model="newHolderUserId"
            clearable />
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.changeHolderModal = false" class="btn-ghost" />
            <x-button :label="__('Save')" icon="o-check" class="btn-primary" wire:click="confirmChangeHolder" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Modal: Manual entry --}}
    <x-app-modal wire:model="manualEntryModal" :title="__('Add Entry')" separator :open="$manualEntryModal">
        <div class="space-y-4">
            <p class="text-sm opacity-60">
                {{ __('Use a positive amount for cash in, negative for cash out.') }}
            </p>
            <x-input
                :label="__('Amount (€)')"
                wire:model="entryAmount"
                type="number"
                :hint="__('Positive = cash in, negative = cash out')" />
            <x-select
                :label="__('Reason')"
                :options="$reasonOptions"
                option-label="name"
                wire:model="entryReason" />
            <x-textarea
                :label="__('Notes')"
                wire:model="entryNotes"
                rows="2"
                :placeholder="__('Optional notes...')" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" @click="$wire.manualEntryModal = false" class="btn-ghost" />
            <x-button :label="__('Save')" icon="o-check" class="btn-primary" wire:click="saveManualEntry" spinner />
        </x-slot:actions>
    </x-app-modal>
</div>
