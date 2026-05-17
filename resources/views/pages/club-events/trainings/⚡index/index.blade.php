<div>
    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    @if ($selectedPackId)
        {{-- SESSION LIST HEADER --}}
        <x-header separator
            :subtitle="$selectedPack?->level?->value . ' · ' . $selectedPack?->type?->value"
            :title="$selectedPack?->name ?? __('Sessions')">
            <x-slot:actions>
                <x-button class="btn-ghost" icon="o-arrow-left" label="{{ __('Back') }}" wire:click="backToList" />
            </x-slot:actions>
        </x-header>
    @else
        {{-- PACK LIST HEADER --}}
        <x-header separator subtitle="{{ __('Manage training packs for the active season') }}"
            title="{{ __('Trainings') }}">
            <x-slot:actions>
                <x-button class="btn-primary" icon="o-plus" label="{{ __('New pack') }}" wire:click="openCreate" />
            </x-slot:actions>
        </x-header>
    @endif

    {{-- ── No active season ────────────────────────────────────────────────── --}}
    @if (! $activeSeason)
        <x-alert class="alert-warning" icon="o-exclamation-triangle"
            title="{{ __('No active season. Activate a season first.') }}" />
    @elseif ($selectedPackId)
        {{-- ================================================================
             SESSION DRILL-DOWN
        ================================================================ --}}
        <div class="space-y-3">
            @forelse ($sessions as $session)
                @php
                    $cancelled = $session->isCancelled();
                @endphp
                <div @class([
                    'flex items-center justify-between rounded-xl border px-4 py-3',
                    'bg-base-100 border-base-200' => ! $cancelled,
                    'bg-base-200/50 border-base-200 opacity-60' => $cancelled,
                ])>
                    <div class="flex items-center gap-4">
                        <div class="text-center">
                            <div class="text-xs font-bold uppercase text-base-content/50">
                                {{ $session->start->translatedFormat('M') }}
                            </div>
                            <div class="text-2xl font-bold leading-none">{{ $session->start->format('d') }}</div>
                            <div class="text-xs text-base-content/50">{{ $session->start->translatedFormat('D') }}
                            </div>
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ $session->start->format('H:i') }}
                                – {{ $session->end->format('H:i') }}
                            </div>
                            <div class="text-xs text-base-content/60">{{ $session->room?->name }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($cancelled)
                            <x-badge
                                value="{{ $session->status === 'cancelled_free' ? __('Free practice') : __('Closed') }}"
                                class="badge-error badge-soft" />
                        @else
                            <x-badge value="{{ __('Scheduled') }}" class="badge-success badge-soft" />
                            <x-button class="btn-ghost btn-sm text-error" icon="o-x-circle"
                                label="{{ __('Cancel') }}" wire:click="openCancel({{ $session->id }})" />
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
                    <x-icon class="mx-auto mb-2 h-10 w-10" name="o-calendar" />
                    <p>{{ __('No sessions generated yet.') }}</p>
                </div>
            @endforelse
        </div>
    @else
        {{-- ================================================================
             PACK LIST — grouped by level
        ================================================================ --}}
        @php $grouped = $packs->groupBy(fn ($p) => $p->level?->value ?? 'Other'); @endphp

        @if ($grouped->isEmpty())
            <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
                <x-icon class="mx-auto mb-2 h-10 w-10" name="o-academic-cap" />
                <p>{{ __('No training packs for this season yet.') }}</p>
                <x-button class="btn-primary mt-4" label="{{ __('Create first pack') }}"
                    wire:click="openCreate" />
            </div>
        @else
            <div class="space-y-8">
                @foreach ($grouped as $level => $items)
                    <section>
                        <div class="mb-3 flex items-center gap-3">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-base-content/50">
                                {{ $level }}
                            </h2>
                            <span class="text-xs text-base-content/30">·
                                {{ $items->count() }} {{ __('pack(s)') }}</span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                            @foreach ($items as $pack)
                                @php
                                    $enrolled = $pack->enrolledCount();
                                    $max = $pack->effectiveMaxParticipants();
                                    $full = $max > 0 && $enrolled >= $max;
                                @endphp
                                <div
                                    class="group flex flex-col overflow-hidden rounded-xl border border-base-200 bg-base-100">
                                    {{-- Card header --}}
                                    <div class="bg-primary/5 px-4 py-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <p class="font-semibold leading-tight text-primary">
                                                    {{ $pack->name }}
                                                </p>
                                                <p class="mt-0.5 text-xs text-primary/60">
                                                    {{ $pack->type?->value }}
                                                    @if ($pack->day_of_week)
                                                        · {{ ['', __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')][$pack->day_of_week] }}
                                                        {{ $pack->start_time ? \Carbon\Carbon::parse($pack->start_time)->format('H:i') : '' }}
                                                    @endif
                                                </p>
                                            </div>
                                            <x-badge value="{{ number_format($pack->price / 100, 0) }}€"
                                                class="badge-primary badge-soft shrink-0" />
                                        </div>
                                    </div>

                                    {{-- Card body --}}
                                    <div class="flex flex-1 flex-col px-4 py-3">
                                        <div class="mb-3 space-y-1 text-[13px] text-base-content/70">
                                            <div class="flex items-center gap-2">
                                                <x-icon class="h-4 w-4 shrink-0 opacity-40" name="o-user" />
                                                {{ $pack->trainer?->full_name ?? __('No coach') }}
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <x-icon class="h-4 w-4 shrink-0 opacity-40" name="o-map-pin" />
                                                {{ $pack->room?->name ?? '—' }}
                                            </div>
                                        </div>

                                        {{-- Capacity --}}
                                        <div class="mb-3">
                                            <div class="mb-1 flex justify-between text-[11px] text-base-content/50">
                                                <span>{{ $enrolled }} / {{ $max ?: '∞' }} {{ __('enrolled') }}</span>
                                                @if ($full)
                                                    <span class="font-medium text-error">{{ __('Full') }}</span>
                                                @endif
                                            </div>
                                            @if ($max > 0)
                                                <progress
                                                    class="progress {{ $full ? 'progress-error' : 'progress-primary' }} h-1"
                                                    max="{{ $max }}" value="{{ $enrolled }}"></progress>
                                            @endif
                                        </div>

                                        {{-- Actions --}}
                                        <div class="mt-auto flex gap-2 border-t border-base-200 pt-2">
                                            <x-button class="btn-ghost btn-sm flex-1 text-xs"
                                                icon="o-calendar-days" label="{{ __('Sessions') }}"
                                                wire:click="viewSessions({{ $pack->id }})" />
                                            <x-button class="btn-ghost btn-sm text-xs" icon="o-pencil"
                                                wire:click="openEdit({{ $pack->id }})" />
                                            <x-button class="btn-ghost btn-sm text-error text-xs" icon="o-trash"
                                                wire:click="deactivatePack({{ $pack->id }})"
                                                wire:confirm="{{ __('Deactivate this pack?') }}" />
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    @endif

    {{-- ================================================================
         WIZARD MODAL (3 steps)
    ================================================================ --}}
    <x-modal :title="$packId ? __('Edit pack') : __('New training pack')" wire:model="wizardOpen" separator>
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
                <x-input label="{{ __('Pack name') }}" placeholder="{{ __('E.g. Tuesday Elite') }}"
                    wire:model="formName" />

                <div class="grid grid-cols-2 gap-4">
                    <x-select :options="$levelOptions" label="{{ __('Level') }}" wire:model="formLevel"
                        placeholder="{{ __('Select…') }}" />
                    <x-select :options="$typeOptions" label="{{ __('Type') }}" wire:model="formType"
                        placeholder="{{ __('Select…') }}" />
                </div>

                <x-select :options="$trainerOptions" label="{{ __('Coach') }}" wire:model="formTrainerId"
                    placeholder="{{ __('No coach') }}" />

                <x-select :options="$roomOptions" label="{{ __('Room') }}" wire:model="formRoomId"
                    placeholder="{{ __('Select…') }}" />

                <x-textarea label="{{ __('Description') }}" placeholder="{{ __('Optional…') }}"
                    wire:model="formDescription" rows="3" />
            </div>
        @endif

        {{-- Step 2 — Planning --}}
        @if ($step === '2')
            <div class="space-y-4">
                <x-select :options="$dayOptions" label="{{ __('Day of week') }}" wire:model.live="formDayOfWeek"
                    placeholder="{{ __('Select…') }}" />

                <div class="grid grid-cols-2 gap-4">
                    <x-input label="{{ __('Start time') }}" type="time" wire:model.live="formStartTime" />
                    <x-input label="{{ __('Duration (min)') }}" type="number" min="15" max="480"
                        wire:model.live="formDurationMinutes" />
                </div>

                {{-- Preview --}}
                @if (count($previewDates) > 0)
                    <div class="rounded-lg bg-primary/5 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-primary/60">
                            {{ __(':count sessions will be generated', ['count' => count($previewDates)]) }}
                        </p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($previewDates as $d)
                                <span class="rounded-md bg-primary/10 px-2 py-0.5 text-[11px] text-primary">
                                    {{ $d->translatedFormat('D d M') }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @elseif ($formDayOfWeek)
                    <x-alert class="alert-warning" icon="o-exclamation-triangle"
                        title="{{ __('No dates can be generated for this season.') }}" />
                @endif
            </div>
        @endif

        {{-- Step 3 — Price --}}
        @if ($step === '3')
            <div class="space-y-4">
                <x-input label="{{ __('Pack price (€)') }}" type="number" min="0" step="0.50"
                    wire:model="formPrice" />
                <x-alert class="alert-info" icon="o-information-circle"
                    title="{{ __('The pack price is added to the subscription. Default: 90€ (1st pack) or 80€ (additional packs).') }}" />

                {{-- Summary --}}
                <div class="rounded-xl border border-base-200 bg-base-100 p-4 text-sm">
                    <h3 class="mb-3 font-semibold">{{ __('Summary') }}</h3>
                    <div class="space-y-1 text-base-content/70">
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
                        <div class="flex justify-between">
                            <span>{{ __('Price') }}</span>
                            <span class="font-semibold text-primary">{{ number_format($formPrice, 2) }} €</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <x-slot:actions>
            @if ($step === '1')
                <x-button label="{{ __('Cancel') }}" wire:click="closeWizard" />
                <x-button class="btn-primary" label="{{ __('Next →') }}" wire:click="nextStep" />
            @elseif ($step === '2')
                <x-button label="{{ __('← Back') }}" wire:click="prevStep" />
                <x-button class="btn-primary" label="{{ __('Next →') }}" wire:click="nextStep" />
            @else
                <x-button label="{{ __('← Back') }}" wire:click="prevStep" />
                <x-button class="btn-primary" icon="o-check"
                    label="{{ $packId ? __('Update') : __('Create pack') }}" wire:click="save" />
            @endif
        </x-slot:actions>
    </x-modal>

    {{-- ================================================================
         CANCELLATION MODAL
    ================================================================ --}}
    <x-modal title="{{ __('Cancel this session') }}" wire:model="cancelModal" separator>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div @class([
                    'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                    'border-warning bg-warning/10' => $cancelType === 'FREE',
                    'border-base-200' => $cancelType !== 'FREE',
                ]) wire:click="$set('cancelType', 'FREE')">
                    <x-icon class="mx-auto mb-1 h-6 w-6 text-warning" name="o-sun" />
                    <p class="text-sm font-semibold">{{ __('Free practice') }}</p>
                    <p class="text-xs text-base-content/60">{{ __('Room open, no coach') }}</p>
                </div>
                <div @class([
                    'cursor-pointer rounded-xl border-2 p-3 text-center transition',
                    'border-error bg-error/10' => $cancelType === 'CLOSED',
                    'border-base-200' => $cancelType !== 'CLOSED',
                ]) wire:click="$set('cancelType', 'CLOSED')">
                    <x-icon class="mx-auto mb-1 h-6 w-6 text-error" name="o-lock-closed" />
                    <p class="text-sm font-semibold">{{ __('Room closed') }}</p>
                    <p class="text-xs text-base-content/60">{{ __('Inaccessible') }}</p>
                </div>
            </div>

            <x-textarea label="{{ __('Note (optional)') }}"
                placeholder="{{ __('E.g. Bank holiday, maintenance…') }}" wire:model="cancelNote" rows="2" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Abandon') }}" wire:click="$set('cancelModal', false)" />
            <x-button class="btn-error" icon="o-x-circle" label="{{ __('Confirm cancellation') }}"
                wire:click="confirmCancel" />
        </x-slot:actions>
    </x-modal>
</div>
