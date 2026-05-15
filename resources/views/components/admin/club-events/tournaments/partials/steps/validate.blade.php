<div class="mt-8 max-w-2xl mx-auto space-y-6 animate-in fade-in duration-500">

    @if ($this->currentTournament?->status?->value === 'locked' || $this->isContractLocked)

        {{-- Already locked --}}
        <div class="text-center p-8 rounded-2xl bg-success/5 border border-success/20">
            <x-icon name="o-lock-closed" class="w-12 h-12 text-success mx-auto mb-3" />
            <h3 class="text-lg font-bold text-success">{{ __('Tournament validated & locked') }}</h3>
            <p class="text-sm text-base-content/60 mt-1">
                {{ __('Name and price are now locked. You can proceed to send invitations.') }}
            </p>
        </div>

        <div class="flex justify-center">
            <x-button
                label="{{ __('Go to Invitations') }}"
                icon="o-arrow-right"
                class="btn-primary"
                wire:click="$set('step', '4')" />
        </div>

    @else

        {{-- Tournament summary recap --}}
        <x-card title="{{ __('Tournament summary') }}" icon="o-clipboard-document-list" shadow>

            @php
                $t = $this->currentTournament;
                $t?->loadMissing('rooms');
                $checks = [
                    ['label' => __('Name defined'), 'ok' => !empty($name)],
                    ['label' => __('Date set'), 'ok' => !empty($tournamentDate)],
                    ['label' => __('Location set'), 'ok' => count($selectedRooms) > 0],
                    ['label' => __('Registration deadline'), 'ok' => !empty($registration_deadline)],
                    ['label' => __('Price defined'), 'ok' => true, 'optional' => true],
                ];
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
                <div class="p-3 rounded-xl bg-base-200/60 space-y-1">
                    <p class="text-xs text-base-content/50 uppercase font-semibold tracking-wide">{{ __('Tournament') }}</p>
                    <p class="font-bold">{{ $name ?: '—' }}</p>
                </div>
                <div class="p-3 rounded-xl bg-base-200/60 space-y-1">
                    <p class="text-xs text-base-content/50 uppercase font-semibold tracking-wide">{{ __('Date & time') }}</p>
                    <p class="font-bold">{{ $tournamentDate ? \Carbon\Carbon::parse($tournamentDate)->format('d/m/Y') : '—' }} {{ $startTime ? 'at ' . $startTime : '' }}</p>
                </div>
                <div class="p-3 rounded-xl bg-base-200/60 space-y-1">
                    <p class="text-xs text-base-content/50 uppercase font-semibold tracking-wide">{{ __('Location') }}</p>
                    <p class="font-bold">{{ $t?->rooms->pluck('name')->join(', ') ?: '—' }}</p>
                </div>
                <div class="p-3 rounded-xl bg-base-200/60 space-y-1">
                    <p class="text-xs text-base-content/50 uppercase font-semibold tracking-wide">{{ __('Registration deadline') }}</p>
                    <p class="font-bold {{ empty($registration_deadline) ? 'text-error' : '' }}">
                        {{ $registration_deadline ? \Carbon\Carbon::parse($registration_deadline)->format('d/m/Y') : __('Not set — required!') }}
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-base-200/60 space-y-1">
                    <p class="text-xs text-base-content/50 uppercase font-semibold tracking-wide">{{ __('Format') }}</p>
                    <p class="font-bold">
                        {{ $matchType === 'double' ? __('Doubles') : __('Singles') }}
                        — {{ $totalSets }} {{ __('sets') }}
                        {{ $nb_poules > 0 ? '— ' . $nb_poules . ' ' . __('pools') : '' }}
                    </p>
                </div>
                <div class="p-3 rounded-xl bg-base-200/60 space-y-1">
                    <p class="text-xs text-base-content/50 uppercase font-semibold tracking-wide">{{ __('Entry fee') }}</p>
                    <p class="font-bold">{{ $price > 0 ? number_format($price, 2) . ' €' : __('Free') }}</p>
                </div>
            </div>

            {{-- Checklist --}}
            <div class="space-y-2">
                @foreach ($checks as $check)
                    @if (!isset($check['optional']) || isset($check['optional']) && !$check['optional'])
                        <div class="flex items-center gap-2 text-sm {{ $check['ok'] ? 'text-success' : 'text-error' }}">
                            <x-icon :name="$check['ok'] ? 'o-check-circle' : 'o-x-circle'" class="w-4 h-4 shrink-0" />
                            <span>{{ $check['label'] }}</span>
                        </div>
                    @endif
                @endforeach
            </div>

        </x-card>

        <x-alert
            title="{{ __('After validation: name and price cannot be changed') }}"
            description="{{ __('To modify these fields, you would need to cancel the tournament. Dates, times, and rooms can still be updated (participants will be notified).') }}"
            icon="o-lock-closed"
            class="alert-warning alert-soft" />

        @php
            $canValidate = !empty($name) && !empty($tournamentDate) && !empty($registration_deadline) && count($selectedRooms) > 0;
        @endphp

        <div class="flex items-center justify-between">
            <x-button
                label="{{ __('Back') }}"
                icon="o-arrow-left"
                class="btn-ghost btn-sm"
                wire:click="$set('step', '2')" />

            <x-button
                label="{{ __('Validate') }}"
                icon="o-lock-closed"
                class="btn-primary"
                wire:click="validateAndLock"
                spinner="validateAndLock"
                :disabled="!$canValidate" />
        </div>

    @endif

</div>
