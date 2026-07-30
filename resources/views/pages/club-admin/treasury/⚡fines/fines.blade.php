<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" />
    </x-slot:breadcrumbs>

    <x-header separator :subtitle="__('Federation fines passed on to members')" :title="__('Fines')">
        <x-slot:actions>
            <x-admin.shared.filters-button :count="count($filterChips)" class="btn-sm" />
            <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('Issue a fine')"
                wire:click="openFineDrawer" />
        </x-slot:actions>
    </x-header>

    <x-admin.shared.filter-chips :chips="$filterChips" />

    @if ($this->fines->isEmpty())
        <x-empty-state icon="o-scale" :heading="__('No fines')"
            :message="__('Fines passed on to members will appear here.')" />
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
                                <span>{{ $fine->created_at?->format('d/m/Y') }}</span>
                                @if ($fine->issuer)
                                    <span class="text-base-content/30">·</span>
                                    <span>{{ __('by') }} {{ $fine->issuer->full_name }}</span>
                                @endif
                                @if ($fine->federation_reference)
                                    <span class="text-base-content/30">·</span>
                                    <span class="font-mono">{{ $fine->federation_reference }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3 sm:justify-end">
                            <div class="text-right">
                                <div class="font-bold tabular-nums">{{ number_format($fine->amount, 2, ',', ' ') }} €</div>
                                @if ($fine->payment)
                                    <x-badge
                                        :value="$fine->payment->status === 'paid' ? __('Paid') : __('Pending')"
                                        class="badge-sm {{ $fine->payment->status === 'paid' ? 'badge-success badge-soft' : 'badge-warning badge-soft' }}" />
                                @endif
                            </div>

                            @if (! $fine->payment || $fine->payment->status !== 'paid')
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
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Status') }}</p>
                <x-select wire:model.live="statusFilter" :placeholder="__('All statuses')"
                    :options="[['id' => 'pending', 'name' => __('Pending')], ['id' => 'paid', 'name' => __('Paid')]]" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- Issue drawer --}}
    <x-drawer wire:model="fineDrawer" :title="__('Issue a fine')" right with-close-button class="w-full max-w-xl">
        <x-form wire:submit="issueFine">
            <x-choices wire:model.live="memberId" :label="__('Member')" single searchable
                :options="$memberOptions" />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-select wire:model.live="reason" :label="__('Reason')" :options="\App\Domains\Shared\Enums\FineReason::getOptions()" />
                <x-input wire:model.live="amount" :label="__('Amount')" type="number" step="0.01" min="0" suffix="€" />
            </div>

            <x-input wire:model="federationReference" :label="__('Federation reference')" :placeholder="__('Optional')" />
            <x-textarea wire:model="description" :label="__('Internal note')"
                :hint="__('Not sent to the member — for the committee only.')" rows="2" />

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
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-50">{{ __('Email preview') }}</p>
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

    {{-- Cancel confirmation --}}
    <x-modal wire:model="cancelModal" :title="__('Cancel this fine?')" separator>
        <div class="space-y-3">
            <p class="text-sm text-base-content/80">
                {{ __('The fine will be removed and its pending payment cancelled. The member will be notified that they no longer owe anything.') }}
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
    </x-modal>
</div>
