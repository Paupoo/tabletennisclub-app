<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

{{-- ================================================================
     PACK WIZARD (3 steps) — a page of its own: it carried a date preview
     and a rebuild confirmation inside a modal of the list.
================================================================ --}}
<div>
    <x-header progress-indicator separator :title="$packId ? __('Edit pack') : __('New training pack')"
        :subtitle="$this->editedPack?->name">
        <x-slot:actions>
            <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Back')"
                :link="$packId ? route('admin.trainings.show', $packId) : route('admin.trainings.index')" />
        </x-slot:actions>
    </x-header>

    <div class="mx-auto max-w-3xl rounded-xl border border-base-300 bg-base-100 p-4 sm:p-6">
        {{-- Step indicators --}}
        <div class="mb-6 flex items-center justify-center gap-2 text-xs">
            @foreach ([1 => __('Pack'), 2 => __('Planning'), 3 => __('Price')] as $n => $label)
                <div @class([
                    'flex items-center gap-1.5 rounded-full px-3 py-1 font-medium transition',
                    'bg-primary text-primary-content' => (int) $step === $n,
                    'bg-base-200 text-base-content/50' => (int) $step !== $n,
                ])>
                    <span>{{ $n }}</span>
                    <span>{{ $label }}</span>
                </div>
                @if ($n < 3)
                    <x-icon class="h-3 w-3 text-base-content/30" name="o-chevron-right" />
                @endif
            @endforeach
        </div>

        {{-- Step 1 — Pack info --}}
        @if ($step === '1')
            <div class="space-y-4">
                {{-- Season selector --}}
                <x-select :options="$seasonOptions" :label="__('Season')" wire:model.live="formSeasonId"
                    :placeholder="__('Select a season…')" />

                <x-input :label="__('Pack name')" :placeholder="__('E.g. Tuesday Elite')"
                    wire:model="formName" />

                <div class="grid grid-cols-2 gap-4">
                    <x-select :options="$levelOptions" :label="__('Level')" wire:model="formLevel"
                        :placeholder="__('Select…')" />
                    <x-select :options="$typeOptions" :label="__('Type')" wire:model.live="formType"
                        :placeholder="__('Select…')" />
                </div>

                {{-- Coach — required for non-free types --}}
                <div>
                    <x-select :options="$trainerOptions" :label="__('Coach')" wire:model="formTrainerId"
                        :placeholder="__('No coach')" />
                    @if ($formType && $formType !== \App\Domains\Shared\Enums\TrainingType::FREE->value && ! $formTrainerId)
                        <p class="mt-1 text-xs text-warning-content">
                            <x-icon class="inline h-3 w-3" name="o-exclamation-triangle" />
                            {{ __('A coach is required for this training type.') }}
                        </p>
                    @elseif ($formType === \App\Domains\Shared\Enums\TrainingType::FREE->value)
                        <p class="mt-1 text-xs text-base-content/40">
                            {{ __('Free practice — no coach needed.') }}
                        </p>
                    @endif
                </div>

                <x-select :options="$roomOptions" :label="__('Room')" wire:model="formRoomId"
                    :placeholder="__('Select…')" />

                <x-textarea :label="__('Description')" :placeholder="__('Optional…')"
                    wire:model="formDescription" rows="3" />

                {{-- Un stage se facture à part, hors cotisation. La case se fige
                     dès le premier inscrit : la basculer changerait d'un coup ce
                     que doivent les inscrits. --}}
                <div class="rounded-xl border border-base-300 p-3">
                    <x-toggle :label="__('Training camp (outside the membership fee)')" wire:model.live="formIsCamp"
                        :disabled="$this->campNatureLocked"
                        :hint="$this->campNatureLocked
                            ? __('Members are already enrolled: this can no longer change. Create a new pack if needed.')
                            : __('Optional and invoiced separately: it never enters the affiliation, nor the attestation for the health insurance.')" />

                    @if ($formIsCamp)
                        <x-toggle class="mt-3" :label="__('Enrolment on approval')" wire:model="formRequiresApproval"
                            :hint="__('Members send a request the committee accepts or refuses, e.g. a camp for young players only.')" />
                    @endif
                </div>
            </div>
        @endif

        {{-- Step 2 — Planning --}}
        @if ($step === '2')
            <div class="space-y-4">
                {{-- Recurrence type toggle --}}
                <div>
                    <p class="mb-2 text-sm font-medium">{{ __('Recurrence') }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <div @class([
                            'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                            'border-primary bg-primary/10' => $formRecurrenceType === 'weekly',
                            'border-base-300' => $formRecurrenceType !== 'weekly',
                        ]) wire:click="$set('formRecurrenceType', 'weekly')">
                            <x-icon class="mx-auto mb-1 h-5 w-5 text-primary" name="o-calendar" />
                            <p class="text-sm font-semibold">{{ __('Once a week') }}</p>
                            <p class="text-xs text-base-content/60">{{ __('Same day every week') }}</p>
                        </div>
                        <div @class([
                            'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                            'border-primary bg-primary/10' => $formRecurrenceType === 'specific_days',
                            'border-base-300' => $formRecurrenceType !== 'specific_days',
                        ]) wire:click="$set('formRecurrenceType', 'specific_days')">
                            <x-icon class="mx-auto mb-1 h-5 w-5 text-primary" name="o-calendar-days" />
                            <p class="text-sm font-semibold">{{ __('Several days') }}</p>
                            <p class="text-xs text-base-content/60">{{ __('Pick multiple weekdays') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Day selection depending on recurrence type --}}
                @if ($formRecurrenceType === 'weekly')
                    <x-select :options="$dayOptions" :label="__('Day of week')"
                        wire:model.live="formDayOfWeek" :placeholder="__('Select…')" />
                @else
                    <div>
                        <p class="mb-2 text-sm font-medium">{{ __('Days of the week') }}</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($dayOptions as $day)
                                <label @class([
                                    'cursor-pointer select-none rounded-full border px-3 py-1 text-xs font-medium transition',
                                    'border-primary bg-primary text-primary-content' => in_array($day['id'], array_map('intval', $formSpecificDays)),
                                    'border-base-300 text-base-content/70 hover:border-primary/50' => ! in_array($day['id'], array_map('intval', $formSpecificDays)),
                                ])>
                                    <input class="sr-only" type="checkbox" wire:model.live="formSpecificDays"
                                        value="{{ $day['id'] }}" />
                                    {{ $day['name'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <x-input :label="__('Start time')" type="time" wire:model.live="formStartTime" />
                    <x-input :label="__('Duration (min)')" type="number" min="15" max="480"
                        wire:model.live="formDurationMinutes" />
                </div>

                {{-- Capacity --}}
                <div>
                    <x-input :label="__('Maximum participants')" type="number" min="1" max="999"
                        wire:model.live="formMaxParticipants" :disabled="$formIsOpenEnrollment"
                        :placeholder="$this->inheritedRoomCapacity
                            ? __('Room capacity: :count', ['count' => $this->inheritedRoomCapacity])
                            : __('Room capacity')" />

                    <p class="mt-1 text-xs text-base-content/50">
                        {{ __('Leave empty to use the training capacity of the selected room.') }}
                    </p>

                    @if ($formType === \App\Domains\Shared\Enums\TrainingType::FREE->value)
                        <label class="flex items-center gap-2 mt-3 cursor-pointer">
                            <x-checkbox wire:model.live="formIsOpenEnrollment" />
                            <span class="text-sm">{{ __('No participant cap (and therefore no waiting list)') }}</span>
                        </label>
                    @endif

                    <label class="mt-3 flex cursor-pointer items-center gap-2">
                        <x-checkbox wire:model.live="formEnrollmentsOpen" />
                        <span class="text-sm">{{ __('Open to member sign-ups') }}</span>
                    </label>

                    <p class="mt-1 text-xs text-base-content/50">
                        {{ __('Unchecked, the pack stays visible but members cannot sign up on their own. The committee can still add them.') }}
                    </p>
                </div>

                {{-- Pack period --}}
                <div>
                    <p class="mb-1 text-xs text-base-content/50">
                        {{ __('Period covered by the pack — prefilled with the season, change it for a camp') }}
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <x-input :label="__('From')" type="date" wire:model.live="formPackStartDate" />
                        <x-input :label="__('To')" type="date" wire:model.live="formPackEndDate" />
                    </div>
                </div>

                {{-- Preview --}}
                @if (count($previewDates) > 0)
                    <div class="rounded-lg bg-primary/5 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary/60">
                            {{ __(':count sessions will be generated', ['count' => count($previewDates)]) }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($previewDates as $d)
                                <span class="group relative inline-flex items-center gap-1 rounded-md bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                    {{ $d->translatedFormat('D d M') }}
                                    <button
                                        class="ml-0.5 rounded-full opacity-50 transition hover:opacity-100 hover:text-error"
                                        :title="__('Exclude this date')"
                                        wire:click="toggleExcludeDate('{{ $d->toDateString() }}')"
                                        wire:key="include-{{ $d->toDateString() }}"
                                        type="button">
                                        ×
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Excluded dates (re-includable) --}}
                    @if (count($formExcludedDates) > 0)
                        <div class="rounded-lg border border-base-300 p-3">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-base-content/40">
                                {{ __(':count excluded', ['count' => count($formExcludedDates)]) }}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($formExcludedDates as $excluded)
                                    <button
                                        class="inline-flex items-center gap-1 rounded-md border border-dashed border-base-300 px-2 py-0.5 text-xs text-base-content/40 line-through transition hover:border-primary hover:text-primary hover:no-underline"
                                        :title="__('Re-include this date')"
                                        wire:click="toggleExcludeDate('{{ $excluded }}')"
                                        wire:key="exclude-{{ $excluded }}"
                                        type="button">
                                        {{ \Carbon\Carbon::parse($excluded)->translatedFormat('D d M') }}
                                        <span class="no-underline">↩</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @elseif ($formDayOfWeek || count($formSpecificDays) > 0)
                    <x-alert class="alert-warning" icon="o-exclamation-triangle"
                        :title="__('No dates can be generated for this period.')" />
                @endif
            </div>
        @endif

        {{-- Step 3 — Price --}}
        @if ($step === '3')
            <div class="space-y-4">
                <x-input :label="__('Pack price (€)')" type="number" min="0" step="0.50"
                    wire:model.live="formPrice" />

                @if ($formIsCamp)
                    <x-alert class="alert-info" icon="o-information-circle"
                        :title="__('The training camp is invoiced on its own, without automatic discount. A member\'s own price can be set when enrolling them.')" />

                    {{-- Ouvrir aux externes est un choix par stage : la date est l'opt-in,
                         et les membres gardent la priorité jusque-là. --}}
                    <div class="rounded-xl border border-base-300 p-3 space-y-3">
                        <p class="text-sm font-medium">{{ __('Non-members') }}</p>
                        <x-datetime :label="__('Open to non-members from')" wire:model="formExternalsOpenOn"
                            :hint="__('Empty: members only. Until that day, members have priority.')" />
                        <x-input :label="__('Price for non-members (€)')" type="number" min="0" step="1"
                            wire:model="formExternalPrice" :placeholder="number_format((float) $formPrice, 0)"
                            :hint="__('Empty: the same price as members.')" />
                    </div>
                @else
                    <x-toggle
                        :label="__('Allow family/multi-pack discount')"
                        wire:model.live="formAllowDiscount"
                        :hint="__('When enabled, a 10€ discount applies per pack for members taking multiple packs or families.')" />

                    <x-alert class="alert-info" icon="o-information-circle"
                        :title="__('The pack price is added to the subscription price.')" />
                @endif

                {{-- Summary --}}
                <div class="rounded-xl border border-base-300 bg-base-100 p-4 text-sm">
                    <h3 class="mb-3 font-semibold">{{ __('Summary') }}</h3>
                    <div class="space-y-1 text-base-content/70">
                        <div class="flex justify-between">
                            <span>{{ __('Season') }}</span>
                            <span class="font-medium text-base-content">
                                {{ collect($seasonOptions)->firstWhere('id', $formSeasonId)['name'] ?? '—' }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span>{{ __('Name') }}</span>
                            <span class="font-medium text-base-content">{{ $formName }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>{{ __('Level / Type') }}</span>
                            <span class="font-medium text-base-content">{{ $formLevel }} / {{ $formType }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>{{ __('Sessions') }}</span>
                            <span class="font-medium text-base-content">{{ count($previewDates) }}
                                {{ __('sessions') }}</span>
                        </div>
                        @if (count($formExcludedDates) > 0)
                            <div class="flex justify-between">
                                <span>{{ __('Excluded') }}</span>
                                <span class="font-medium text-base-content/50">
                                    {{ count($formExcludedDates) }} {{ __('date(s)') }}
                                </span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span>{{ __('Price') }}</span>
                            <span class="font-semibold text-primary">{{ number_format($formPrice, 2) }} €</span>
                        </div>
                        <div class="flex justify-between">
                            <span>{{ __('Discount') }}</span>
                            <span class="font-medium text-base-content">
                                {{ $formAllowDiscount && ! $formIsCamp ? __('Enabled (−10€)') : __('Disabled') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-base-300 pt-4">
            @if ($step === '1')
                <x-button :label="__('Cancel')"
                    :link="$packId ? route('admin.trainings.show', $packId) : route('admin.trainings.index')" />
                <x-button class="btn-primary" :label="__('Next →')" wire:click="nextStep" spinner />
            @elseif ($step === '2')
                <x-button :label="__('← Back')" wire:click="prevStep" />
                <x-button class="btn-primary" :label="__('Next →')" wire:click="nextStep" spinner />
            @else
                <x-button :label="__('← Back')" wire:click="prevStep" />
                <x-button class="btn-primary" icon="o-check"
                    label="{{ $packId ? __('Update') : __('Create pack') }}" wire:click="save" spinner />
            @endif
        </div>
    </div>

    <x-app-modal wire:model="regenerateModal" :title="__('Rebuild the sessions?')" separator :open="$regenerateModal">
        <p>{{ __('You changed when or where this pack takes place. Its existing sessions do not move on their own.') }}</p>

        <div class="p-3 mt-3 text-sm rounded-lg bg-warning/10">
            <p>{{ __(':count session(s) still to come will be deleted and generated again on the new slot.', ['count' => $regenerationImpact['deleting']]) }}</p>
            <p>{{ __(':count past or already cancelled session(s) are left untouched.', ['count' => $regenerationImpact['keeping']]) }}</p>
        </div>

        <p class="mt-2 text-xs text-base-content/60">
            {{ __('Past sessions carry the attendance you recorded, and a cancelled session was announced with its own wording — neither is rebuilt.') }}
        </p>

        @if ($regenerationImpact['members'] > 0)
            <label class="flex items-center gap-2 mt-3 cursor-pointer">
                <x-checkbox wire:model="notifyMembersOfChange" />
                <span class="text-sm">
                    {{ __('Email the :count enrolled member(s) about the new schedule', ['count' => $regenerationImpact['members']]) }}
                </span>
            </label>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('regenerateModal', false)" />
            <x-button :label="__('Rebuild the sessions')" class="btn-warning" wire:click="confirmRegeneration" spinner />
        </x-slot:actions>
    </x-app-modal>
</div>
