<div class="mt-8 space-y-6 animate-in fade-in duration-500" wire:poll.30s="$refresh">

    @if ($this->isLaunched)
        <div class="flex items-center gap-3 p-4 rounded-xl bg-base-200 border border-base-300 text-sm">
            <x-icon name="o-lock-closed" class="w-5 h-5 shrink-0 text-base-content/40" />
            <p class="text-base-content/60">{{ __('The tournament has been launched. Registration management is read-only.') }}</p>
        </div>
    @endif

    <x-card title="{{ __('Registrated people') }}" shadow>
        <x-slot:menu>
            <div class="flex gap-2">
                @if (! $this->isLaunched)
                    <x-button
                        :label="!$this->registrationClosed ? __('Close Registrations') : __('Open Registrations')"
                        :icon="!$this->registrationClosed ? 'o-lock-closed' : 'o-lock-open'"
                        class="btn-primary btn-sm"
                        wire:click="openToggleRegistrationsModal"
                        :disabled="!$tournamentId" />
                    @if (! $this->registrationClosed && ! ($maxUsers > 0 && $this->registrations->count() >= $maxUsers))
                        <x-button
                            label="{{ __('Register member') }}"
                            icon="o-user-plus"
                            class="btn-ghost btn-sm"
                            wire:click="$set('showRegisterModal', true)"
                            :disabled="!$tournamentId" />
                    @endif
                    <x-button
                        label="Bulk actions"
                        icon="o-funnel"
                        class="btn-ghost btn-sm"
                        @click="$wire.bulkDrawer = true"
                        ::class="{ 'btn-disabled opacity-50': $wire.selectedPeople.length === 0 }" />
                @endif
            </div>
        </x-slot:menu>

        @php
        $isPaidTournament = $this->currentTournament?->isPaid() ?? false;
        $headers = [
            ['key' => 'name',          'label' => __('Player'),      'sortable' => true],
            ['key' => 'ranking',       'label' => __('Ranking'),     'sortable' => true],
            ['key' => 'status',        'label' => __('Presence'),    'sortable' => true],
            ['key' => 'registered_at', 'label' => __('Registered'),  'sortable' => true],
        ];
        if ($isPaidTournament) {
            $headers[] = ['key' => 'has_paid', 'label' => __('Payment'), 'sortable' => false];
        }
    @endphp
    <x-table wire:model.live="selectedPeople" :headers="$headers" :rows="$this->registrations"
        :sort-by="$sortBy" selectable>
            @scope('cell_status', $row)
                @if (! $this->isLaunched && in_array($row['status'], ['registered', 'spot_offered']))
                    <div class="flex items-center gap-1">
                        <x-button
                            icon="o-check"
                            class="btn-ghost btn-xs text-success"
                            tooltip="{{ __('Confirm') }}"
                            wire:click="confirmPresence({{ $row['id'] }})"
                            wire:loading.attr="disabled" />
                        <x-button
                            icon="o-no-symbol"
                            class="btn-ghost btn-xs text-warning"
                            tooltip="{{ __('No show') }}"
                            wire:click="markNoShow({{ $row['id'] }})" />
                    </div>
                @else
                    <x-badge :value="$row['status']"
                        :class="match($row['status']) {
                            'confirmed' => 'badge-success',
                            'no_show'   => 'badge-warning',
                            'cancelled' => 'badge-error',
                            default     => 'badge-ghost',
                        }" class="badge-sm" />
                @endif
            @endscope

            @scope('cell_registered_at', $row)
                <span class="text-xs text-base-content/60">
                    {{ $row['registered_at'] ? \Carbon\Carbon::parse($row['registered_at'])->format('d/m/Y H:i') : '—' }}
                </span>
            @endscope

            @scope('cell_has_paid', $row)
                @if($row['has_paid'])
                    <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" icon="o-check-circle" />
                @elseif($row['qr_confirmed'])
                    <x-badge value="{{ __('QR seen') }}" class="badge-info badge-sm" icon="o-eye" />
                @elseif(! $row['payment_id'] && ! ($this->currentTournament?->isPaid() ?? false))
                    <x-badge value="{{ __('Free') }}" class="badge-ghost badge-sm" />
                @else
                    @if (! $this->isLaunched)
                        <div class="flex items-center gap-1">
                            <x-button
                                icon="o-qr-code"
                                class="btn-ghost btn-xs"
                                tooltip="{{ __('QR / bank transfer') }}"
                                wire:click="openQrModal({{ $row['id'] }})" />
                            <x-button
                                icon="o-currency-euro"
                                class="btn-ghost btn-xs text-success"
                                tooltip="{{ __('Cash') }}"
                                wire:click="openCashConfirmModal({{ $row['id'] }})" />
                        </div>
                    @else
                        <x-badge value="{{ __('Pending') }}" class="badge-warning badge-sm" icon="o-clock" />
                    @endif
                @endif
            @endscope

            @scope('actions', $row)
                @if (! $this->isLaunched)
                    <x-button icon="o-trash" class="btn-ghost btn-sm text-error"
                        tooltip-left="{{ __('Cancel registration') }}"
                        wire:click="cancelUserRegistration({{ $row['id'] }})" />
                @endif
            @endscope
        </x-table>
    </x-card>

    @php
        $registrationCount = $this->registrations->count();
        $capacity = $maxUsers > 0 ? $maxUsers : $this->simulation->totalPlayers;
        $waitlistCount = $this->waitlist->count();
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <x-stat title="Inscrits" :value="$registrationCount . ($maxUsers > 0 ? ' / ' . $maxUsers : '')" icon="o-user-group" />
        <x-stat title="Places restantes" :value="$maxUsers > 0 ? max(0, $maxUsers - $registrationCount) : '∞'" icon="o-receipt-percent" />
        <x-stat title="Confirmés"
            :value="$this->registrations->filter(fn ($r) => $r['status'] === 'confirmed')->count()"
            icon="o-check-badge" />
    </div>

    {{-- Waiting list --}}
    @if ($waitlistCount > 0)
        <x-card title="{{ __('Waiting list') }}" shadow class="mt-6">
            <x-slot:menu>
                <div class="flex gap-2">
                    <x-badge value="{{ $waitlistCount }} {{ __('waiting') }}" class="badge-warning" />
                    <x-button
                        label="{{ __('Add to waitlist') }}"
                        icon="o-user-plus"
                        class="btn-ghost btn-sm"
                        wire:click="$set('showRegisterModal', true)"
                        :disabled="!$tournamentId" />
                </div>
            </x-slot:menu>

            <div class="space-y-0">
                <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 opacity-50 text-xs px-2">
                    <span class="w-6 text-center">#</span>
                    <span class="flex-1 ml-2">{{ __('Player') }}</span>
                    <span class="w-16 text-right">{{ __('Rank') }}</span>
                    <span class="w-28 text-right">{{ __('Registered at') }}</span>
                    <span class="w-20"></span>
                </div>
                @foreach ($this->waitlist as $entry)
                    <div wire:key="waitlist-{{ $entry['id'] }}"
                        class="flex items-center gap-2 border-b border-base-300/30 py-2 px-2 hover:bg-base-200/40 text-sm">
                        <span class="w-6 text-center font-mono font-bold text-warning">{{ $entry['position'] }}</span>
                        <span class="flex-1 font-medium truncate">{{ $entry['name'] }}</span>
                        <span class="w-16 text-right font-mono text-xs opacity-60">{{ $entry['ranking'] }}</span>
                        <span class="w-28 text-right text-xs opacity-50">
                            {{ \Carbon\Carbon::parse($entry['registered_at'])->format('d/m H:i') }}
                        </span>
                        @if (! $this->isLaunched)
                            <div class="w-20 flex justify-end gap-1">
                                <x-button icon="o-arrow-up-circle" class="btn-ghost btn-xs text-success"
                                    tooltip="{{ __('Promote to registered') }}"
                                    wire:click="promoteFromWaitlist({{ $entry['id'] }})"
                                    :disabled="$maxUsers > 0 && $this->registrations->count() >= $maxUsers" />
                                <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error"
                                    tooltip="{{ __('Remove from waitlist') }}"
                                    wire:click="removeFromWaitlist({{ $entry['id'] }})" />
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

</div>

{{-- ── Pair composition (doubles only, club mode) ─────────────────── --}}
@if ($this->currentTournament?->match_type === 'double' && $this->currentTournament?->doubles_registration_mode === 'club')
    <x-card title="{{ __('Pair composition') }}" shadow class="mt-6">
        <x-slot:menu>
            @if (! $this->isLaunched)
                <span class="text-xs text-base-content/50">{{ count($this->pairs) }} {{ __('pairs') }} &mdash; {{ count($this->unpaired) }} {{ __('unpaired') }}</span>
            @endif
        </x-slot:menu>

        {{-- Existing pairs --}}
        @if (! empty($this->pairs))
            <div class="space-y-1 mb-4">
                @foreach ($this->pairs as $pair)
                    <div wire:key="pair-{{ $pair['id'] }}" class="flex items-center justify-between p-2 rounded-lg bg-base-200/50 text-sm">
                        <div class="flex items-center gap-2">
                            <x-icon name="o-user-group" class="w-4 h-4 text-primary shrink-0" />
                            <span class="font-medium">{{ $pair['p1_name'] }}</span>
                            <span class="text-base-content/40">&amp;</span>
                            <span class="font-medium">{{ $pair['p2_name'] }}</span>
                        </div>
                        @if (! $this->isLaunched)
                            <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error"
                                tooltip="{{ __('Delete pair') }}"
                                wire:click="deletePair({{ $pair['id'] }})" />
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Create pair form --}}
        @if (! $this->isLaunched && ! empty($this->unpaired))
            <div class="flex items-end gap-3 mt-2">
                <x-select label="{{ __('Player 1') }}" wire:model.live="pairPlayer1Id"
                    :options="$this->unpaired" placeholder="{{ __('Select…') }}" class="flex-1" />
                <x-select label="{{ __('Player 2') }}" wire:model.live="pairPlayer2Id"
                    :options="$this->unpaired" placeholder="{{ __('Select…') }}" class="flex-1" />
                <x-button label="{{ __('Create pair') }}" icon="o-user-group" class="btn-primary btn-sm mb-0.5"
                    wire:click="createPair" spinner="createPair"
                    :disabled="! $pairPlayer1Id || ! $pairPlayer2Id" />
            </div>
        @elseif (! $this->isLaunched && empty($this->unpaired) && empty($this->pairs))
            <p class="text-sm text-base-content/50">{{ __('No registered players yet.') }}</p>
        @elseif (! $this->isLaunched && empty($this->unpaired))
            <p class="text-sm text-success/80 flex items-center gap-1.5">
                <x-icon name="o-check-circle" class="w-4 h-4" /> {{ __('All registered players are paired.') }}
            </p>
        @endif
    </x-card>
@endif

{{-- Manual register modal --}}
<x-modal wire:model="showRegisterModal" title="{{ __('Register a member') }}" class="backdrop-blur">
    <div class="space-y-4">
        <x-select
            label="{{ __('Select member') }}"
            wire:model.live="memberToRegister"
            :options="$this->registerableMembersOptions"
            placeholder="{{ __('Search a member…') }}"
            searchable />

        @if ($memberToRegister && $this->currentTournament?->max_users > 0 && $this->registrations->count() >= $this->currentTournament->max_users)
            <x-alert
                title="{{ __('Tournament is full') }}"
                description="{{ __('This player will be added to the waiting list.') }}"
                icon="o-exclamation-triangle"
                class="alert-warning alert-soft" />
        @endif
    </div>
    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" wire:click="$set('showRegisterModal', false)" />
        <x-button label="{{ __('Register') }}" icon="o-user-plus" class="btn-primary"
            wire:click="registerMember" spinner="registerMember"
            :disabled="!$memberToRegister" />
    </x-slot:actions>
</x-modal>

{{-- ── Close registrations modal ─────────────────────────────── --}}
<x-modal wire:model="showCloseRegistrationsModal" title="{{ __('Close registrations?') }}" class="backdrop-blur">
    <div class="space-y-4">
        <div class="flex items-start gap-3 p-4 bg-error/10 border border-error/20 rounded-xl text-sm text-error">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5" />
            <div class="space-y-1">
                <p class="font-semibold">{{ __('This action is irreversible.') }}</p>
                <p>{{ __('All players currently on the waiting list will be removed and notified by email. They will not automatically regain their position if registrations are reopened.') }}</p>
            </div>
        </div>

        @php $waitlistCount = collect($this->waitlist ?? [])->count(); @endphp
        @if ($waitlistCount > 0)
            <p class="text-sm text-gray-600">
                {{ trans_choice('1 person will be removed from the waitlist.|:count people will be removed from the waitlist.', $waitlistCount, ['count' => $waitlistCount]) }}
            </p>
        @else
            <p class="text-sm text-gray-500">{{ __('The waiting list is currently empty.') }}</p>
        @endif
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" wire:click="$set('showCloseRegistrationsModal', false)" />
        <x-button label="{{ __('Close registrations') }}" icon="o-lock-closed" class="btn-error"
            wire:click="confirmCloseRegistrations" />
    </x-slot:actions>
</x-modal>

{{-- ── Open registrations modal ──────────────────────────────── --}}
<x-modal wire:model="showOpenRegistrationsModal" title="{{ __('Reopen registrations?') }}" class="backdrop-blur">
    <div class="p-4 bg-warning/10 border border-warning/20 rounded-xl flex items-start gap-3 text-sm">
        <x-icon name="o-information-circle" class="w-5 h-5 shrink-0 mt-0.5 text-warning" />
        <p>{{ __('Reopening registrations will set the tournament back to "published" status. The tournament cannot be started until registrations are closed again.') }}</p>
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" wire:click="$set('showOpenRegistrationsModal', false)" />
        <x-button label="{{ __('Reopen registrations') }}" icon="o-lock-open" class="btn-warning"
            wire:click="confirmOpenRegistrations" />
    </x-slot:actions>
</x-modal>

{{-- ── QR / bank transfer modal ──────────────────────────── --}}
<x-modal wire:model="showQrModal" title="{{ __('Payment by QR / Bank Transfer') }}" separator box-class="max-w-lg">
    @if($qrCodeData)
    <div class="flex flex-col items-center gap-4">
        <img src="{{ $qrCodeData }}" alt="SEPA QR" class="w-48 h-48 rounded-xl border border-base-200" />
        <div class="w-full space-y-2 text-sm">
            <div class="flex justify-between py-1 border-b border-base-200">
                <span class="opacity-60">{{ __('Beneficiary') }}</span>
                <span class="font-semibold">{{ $qrPaymentDetails['beneficiary'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-base-200">
                <span class="opacity-60">{{ __('IBAN') }}</span>
                <span class="font-mono">{{ $qrPaymentDetails['iban'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-base-200">
                <span class="opacity-60">{{ __('BIC') }}</span>
                <span class="font-mono">{{ $qrPaymentDetails['bic'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1 border-b border-base-200">
                <span class="opacity-60">{{ __('Reference') }}</span>
                <span class="font-mono text-primary font-bold">{{ $qrPaymentDetails['reference'] ?? '' }}</span>
            </div>
            <div class="flex justify-between py-1">
                <span class="opacity-60">{{ __('Amount') }}</span>
                <span class="font-black text-lg">{{ number_format($qrPaymentDetails['amount_due'] ?? 0, 2, ',', ' ') }} €</span>
            </div>
        </div>
        <p class="text-xs text-center opacity-50">{{ __('Scan the QR code with your banking app or use the details above.') }}</p>
    </div>
    @endif
    <x-slot:actions>
        <x-button label="{{ __('Close') }}" wire:click="$set('showQrModal', false)" class="btn-ghost" />
        @if($paymentActionUserId)
            <x-button
                label="{{ __('I saw the payment succeed') }}"
                icon="o-eye"
                class="btn-info btn-sm"
                tooltip="{{ __('Mark that you visually confirmed the transfer on screen. The treasurer will reconcile it later.') }}"
                wire:click="markQrConfirmed({{ $paymentActionUserId }})"
                spinner="markQrConfirmed" />
        @endif
    </x-slot:actions>
</x-modal>

{{-- ── Cash payment confirm modal ────────────────────────── --}}
<x-modal wire:model="showCashConfirmModal" title="{{ __('Confirm cash payment') }}" separator>
    <div class="flex items-start gap-3 p-4 bg-success/10 border border-success/20 rounded-xl text-sm">
        <x-icon name="o-currency-euro" class="w-5 h-5 shrink-0 mt-0.5 text-success" />
        <div>
            <p class="font-semibold">{{ __('Record a cash payment?') }}</p>
            <p class="opacity-70 mt-1">{{ __('This will mark the player as paid, record the amount in the cash register, and close their pending payment.') }}</p>
        </div>
    </div>
    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" wire:click="$set('showCashConfirmModal', false)" class="btn-ghost" />
        <x-button label="{{ __('Confirm cash payment') }}" icon="o-currency-euro" class="btn-success"
            wire:click="confirmCashPayment" spinner />
    </x-slot:actions>
</x-modal>

