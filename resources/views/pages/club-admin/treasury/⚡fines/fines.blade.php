<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :subtitle="__('Provincial committee fines passed on to members, who pay the committee directly')" :title="__('Fines')">
        <x-slot:actions>
            <x-admin.shared.filters-button :count="count($filterChips)" class="btn-sm" />
            @can('fines.issue')
                <x-button class="btn-ghost btn-sm" icon="o-building-library" :label="__('Provincial committee')"
                    wire:click="openCreditorDrawer" />
                <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Issue a fine')"
                    wire:click="openFineDrawer" />
            @endcan
        </x-slot:actions>
    </x-header>

    @can('fines.issue')
        @if (! $this->creditor->isConfigured())
            <x-alert class="alert-warning mb-4" icon="o-exclamation-triangle"
                :title="__('Enter the provincial committee account before issuing a fine.')">
                <x-slot:actions>
                    <x-button class="btn-sm" :label="__('Enter it')" wire:click="openCreditorDrawer" />
                </x-slot:actions>
            </x-alert>
        @endif
    @endcan

    <x-admin.shared.filter-chips :chips="$filterChips" />

    @if ($this->fines->isEmpty())
        <x-admin.shared.list-empty-state
            icon="o-scale"
            :heading="__('No fines')"
            :filtered="count($filterChips) > 0">
            {{ __('Fines passed on to members will appear here.') }}
        </x-admin.shared.list-empty-state>
    @else
        <x-card class="!p-0">
            <div class="divide-y divide-base-200">
                @foreach ($this->fines as $fine)
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate font-semibold">{{ $fine->user?->full_name ?? '—' }}</span>
                                <x-badge :value="$fine->reason->label()" class="badge-warning badge-soft badge-sm" />
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-base-content/60">
                                @if ($fine->event_label || $fine->event_date)
                                    <span>{{ collect([$fine->event_label, $fine->event_date?->format('d/m/Y')])->filter()->implode(' – ') }}</span>
                                    <span class="text-base-content/30">·</span>
                                @endif
                                <span>{{ __('issued on :date', ['date' => $fine->created_at?->format('d/m/Y')]) }}</span>
                                @if ($fine->issuer)
                                    <span>{{ __('by') }} {{ $fine->issuer->full_name }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3 sm:justify-end">
                            <div class="text-right">
                                <div class="font-bold tabular-nums">{{ number_format($fine->amount, 2, ',', ' ') }} €</div>
                                @if ($fine->payment_deadline)
                                    <div @class(['text-xs', 'text-base-content/60' => $fine->isPayable(), 'text-base-content/40' => ! $fine->isPayable()])>
                                        {{ __('deadline :date', ['date' => $fine->payment_deadline->format('d/m/Y')]) }}
                                    </div>
                                @endif
                            </div>

                            @if ($fine->payment?->status !== 'paid' && auth()->user()->can('fines.cancel'))
                                <x-dropdown icon="o-ellipsis-vertical" class="btn-ghost btn-sm btn-circle" right>
                                    <x-menu-item icon="o-x-circle" :title="__('Cancel this fine')"
                                        wire:click="confirmCancel({{ $fine->id }})" />
                                </x-dropdown>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="mt-6">{{ $this->fines->links() }}</div>
    @endif

    {{-- Filter drawer --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Reason') }}</p>
                <x-select wire:model.live="reasonFilter" :placeholder="__('All reasons')"
                    :options="\App\Domains\Shared\Enums\FineReason::getOptions()" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- Issue drawer --}}
    <x-drawer wire:model="fineDrawer" :title="__('Issue a fine')" right with-close-button class="w-full max-w-xl">
        <x-form wire:submit="issueFine">
            <x-choices wire:model.live="memberId" :label="__('Member')" single searchable
                :options="$memberOptions" />

            <x-select wire:model.live="reason" :label="__('Reason')" :options="\App\Domains\Shared\Enums\FineReason::getOptions()" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input wire:model="eventLabel" :label="__('Tournament or match')" :placeholder="__('As written by the committee')" />
                </div>
                <x-input wire:model="eventDate" :label="__('Event date')" type="date" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input wire:model.live="amount" :label="__('Total to pay')" type="number" step="0.01" min="0" suffix="€"
                    :hint="__('Fine and entry fee included, as the committee asks')" />
                <x-input wire:model="paymentDeadline" :label="__('Payment deadline')" type="date"
                    :hint="__('Past it, the player loses their qualification')" />
            </div>

            <div>
                <x-textarea wire:model.live.debounce.500ms="pedagogicalMessage" :label="__('Message to the member')"
                    :hint="__('Pre-filled suggestion — edit it freely. It is sent as-is in the email.')" rows="7" />
                @if ($messageEdited)
                    <x-button class="btn-ghost btn-xs mt-1" icon="o-arrow-path"
                        :label="__('Reset to suggested message')" wire:click="resetMessage" />
                @endif
            </div>

            {{-- Live preview of what the member will read --}}
            <div class="rounded-xl border border-base-300 bg-base-200/40 p-4">
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Email preview') }}</p>
                <p class="mb-3 text-sm font-bold">{{ __('A fine has been issued') }}</p>
                <p class="whitespace-pre-line text-sm text-base-content/80">{{ $pedagogicalMessage }}</p>
                @if ($amount)
                    <p class="mt-3 border-t border-base-300 pt-3 text-sm">
                        <span class="opacity-60">{{ __('Amount due') }}:</span>
                        <span class="font-bold">{{ number_format((float) $amount, 2, ',', ' ') }} €</span>
                    </p>
                @endif
            </div>

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('fineDrawer', false)" />
                <x-button class="btn-primary" :label="__('Issue the fine and notify')" type="submit"
                    spinner="issueFine" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    {{-- Provincial committee details --}}
    <x-drawer wire:model="creditorDrawer" :title="__('Provincial committee')" right with-close-button class="w-full max-w-xl">
        <x-form wire:submit="saveCreditor">
            <p class="text-sm text-base-content/70">
                {{ __('Members pay their fines to this account directly. Copy it from the committee mail; it only changes when the committee changes bank.') }}
            </p>

            <x-input wire:model="creditorName" :label="__('Beneficiary')" placeholder="CPBBW" />
            <x-input wire:model="creditorIban" label="IBAN" placeholder="BE00 0000 0000 0000" />

            <p class="pt-2 text-xs font-semibold uppercase tracking-widest text-muted">{{ __('Contact for questions') }}</p>
            <x-input wire:model="contactName" :label="__('Name')" :placeholder="__('Optional')" />
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input wire:model="contactEmail" :label="__('Email')" type="email" :placeholder="__('Optional')" />
                <x-input wire:model="contactPhone" :label="__('Phone')" :placeholder="__('Optional')" />
            </div>

            <x-slot:actions>
                <x-button :label="__('Cancel')" wire:click="$set('creditorDrawer', false)" />
                <x-button class="btn-primary" :label="__('Save')" type="submit" spinner="saveCreditor" />
            </x-slot:actions>
        </x-form>
    </x-drawer>

    {{-- Cancel confirmation --}}
    <x-app-modal wire:model="cancelModal" :title="__('Cancel this fine?')" separator :open="$cancelModal">
        <div class="space-y-3">
            <p class="text-sm text-base-content/80">
                {{ __('The fine will be removed. The member will be notified that they no longer owe anything for it.') }}
            </p>
            @if ($this->cancelTarget)
                <div class="rounded-xl border border-base-300 bg-base-200/40 p-3 text-sm">
                    <div class="font-semibold">{{ $this->cancelTarget->user?->full_name ?? '—' }}</div>
                    <div class="mt-0.5 text-base-content/60">
                        {{ $this->cancelTarget->reason->label() }}
                        · {{ number_format($this->cancelTarget->amount, 2, ',', ' ') }} €
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button :label="__('Keep the fine')" wire:click="$set('cancelModal', false)" />
            <x-button class="btn-error" icon="o-x-circle" :label="__('Cancel the fine')"
                wire:click="cancelFine" spinner="cancelFine" />
        </x-slot:actions>
    </x-app-modal>
</div>
