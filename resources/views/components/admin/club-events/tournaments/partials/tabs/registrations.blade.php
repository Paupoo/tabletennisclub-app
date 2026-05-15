<x-tab name="3" label="{{ __('Registrations') }}" icon="o-users">
    <div class="mt-8 space-y-6 animate-in fade-in duration-500">

        <x-card title="{{ __('Registrated people') }}" shadow>
            <x-slot:menu>
                <div class="flex gap-2">
                    <x-button
                        :label="!$this->registrationClosed ? __('Close Registrations') : __('Open Registrations')"
                        :icon="!$this->registrationClosed ? 'o-lock-closed' : 'o-lock-open'"
                        class="btn-primary btn-sm"
                        wire:click="openToggleRegistrationsModal"
                        :disabled="!$tournamentId" />
                    <x-button
                        label="Bulk actions"
                        icon="o-funnel"
                        class="btn-ghost btn-sm"
                        @click="$wire.bulkDrawer = true"
                        ::class="{ 'btn-disabled opacity-50': $wire.selectedPeople.length === 0 }" />
                </div>
            </x-slot:menu>

            @php
            $isPaidTournament = $this->currentTournament?->isPaid() ?? false;
            $headers = [
                ['key' => 'name', 'label' => 'Joueur'],
                ['key' => 'ranking', 'label' => 'Classement'],
                ['key' => 'status', 'label' => 'Statut'],
            ];
            if ($isPaidTournament) {
                $headers[] = ['key' => 'has_paid', 'label' => 'Paiement'];
            }
        @endphp
        <x-table wire:model.live="selectedPeople" :headers="$headers" :rows="$this->registrations" selectable>
                @scope('cell_status', $row)
                    <x-badge :value="$row['status'] === 'spot_offered' ? __('Spot offered') : $row['status']"
                        :class="match($row['status']) {
                            'confirmed'    => 'badge-success',
                            'spot_offered' => 'badge-info',
                            'no_show'      => 'badge-warning',
                            'cancelled'    => 'badge-error',
                            default        => 'badge-ghost',
                        }" class="badge-sm" />
                @endscope

                @scope('cell_has_paid', $row)
                    @if($row['has_paid'])
                        <x-badge value="{{ __('Paid') }}" class="badge-success badge-sm" icon="o-check-circle" />
                    @else
                        <x-badge value="{{ __('Pending') }}" class="badge-warning badge-sm" icon="o-clock" />
                    @endif
                @endscope

                @scope('actions', $row)
                    <div class="flex flex-row">
                        <x-button icon="o-check" class="btn-ghost btn-sm text-success"
                            tooltip-left="{{ __('Confirm presence') }}"
                            wire:click="confirmPresence({{ $row['id'] }})" wire:loading.attr="disabled" />
                        <x-button icon="o-no-symbol" class="btn-ghost btn-sm text-warning"
                            tooltip-left="{{ __('No show') }}"
                            wire:click="markNoShow({{ $row['id'] }})" />
                        <x-button icon="o-trash" class="btn-ghost btn-sm text-error"
                            tooltip-left="{{ __('Cancel registration') }}"
                            wire:click="cancelUserRegistration({{ $row['id'] }})" />
                    </div>
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
                    <x-badge value="{{ $waitlistCount }} {{ __('waiting') }}" class="badge-warning" />
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
                            <div class="w-20 flex justify-end gap-1">
                                <x-button icon="o-arrow-up-circle" class="btn-ghost btn-xs text-success"
                                    tooltip="{{ __('Promote to registered') }}"
                                    wire:click="promoteFromWaitlist({{ $entry['id'] }})"
                                    :disabled="$maxUsers > 0 && $this->registrations->count() >= $maxUsers" />
                                <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error"
                                    tooltip="{{ __('Remove from waitlist') }}"
                                    wire:click="removeFromWaitlist({{ $entry['id'] }})" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>

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
</x-tab>
