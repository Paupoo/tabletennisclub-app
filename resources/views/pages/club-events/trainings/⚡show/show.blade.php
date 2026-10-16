<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

{{-- ================================================================
     PACK SHEET — one screen answers "who is in it, and is it working?"
     Only the open tab is mounted: a click in one of them no longer
     re-renders the roster, the sessions and the attendance grid at once.
================================================================ --}}
<div>
    {{-- The subtitle is assembled from what exists: a pack without a level
         was showing a leading separator. --}}
    <x-header progress-indicator separator
        :subtitle="implode(' · ', array_filter([$selectedPack?->level?->label, $selectedPack?->type?->label(), $selectedPack?->season?->name]))"
        :title="$selectedPack?->name ?? __('Training pack')">
        <x-slot:actions>
            @if ($this->mayManage)
                <x-button class="btn-primary btn-sm" icon="o-user-plus" :label="__('Add a member')"
                    wire:click="openAddMember" />
                <x-button class="btn-ghost btn-sm" icon="o-pencil" :label="__('Edit')"
                    :link="route('admin.trainings.edit', $packId)" />
            @endif
            <x-button class="btn-ghost" icon="o-arrow-left" :label="__('Back')"
                :link="route('admin.trainings.index')" />
        </x-slot:actions>
    </x-header>

    @php
        $summary = $this->packSummary;
    @endphp

    {{-- ── Identity — until now these facts were only legible in the edit form. --}}
    <div class="rounded-xl border border-base-300 bg-base-100 p-4">
        <div class="flex flex-wrap items-center gap-2">
            @unless ($selectedPack?->is_active)
                <x-badge :value="__('Withdrawn')" class="badge-warning badge-soft" icon="o-eye-slash" />
            @endunless
            @if ($selectedPack?->is_camp)
                <x-badge :value="__('Training camp')" class="badge-primary badge-soft" icon="o-sun" />
                @if ($selectedPack->requires_approval)
                    <x-badge :value="__('Enrolment on approval')" class="badge-neutral badge-soft" icon="o-shield-check" />
                @endif
            @endif
            <x-badge :value="$selectedPack?->enrollments_open ? __('Enrolments open') : __('Enrolments closed')"
                class="{{ $selectedPack?->enrollments_open ? 'badge-success' : 'badge-neutral' }} badge-soft"
                :icon="$selectedPack?->enrollments_open ? 'o-lock-open' : 'o-lock-closed'" />
            @if ($selectedPack?->is_open_enrollment)
                <x-badge :value="__('Self-service')" class="badge-info badge-soft" icon="o-hand-raised" />
            @endif
            @if ($selectedPack?->eventPost?->status->value === 'PUBLISHED')
                <x-badge :value="__('On website')" class="badge-success badge-soft" icon="o-globe-alt" />
            @endif
        </div>

        <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <dt class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Coach') }}</dt>
                <dd class="mt-0.5 text-sm">{{ $selectedPack?->trainer?->full_name ?? __('No coach') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Room') }}</dt>
                <dd class="mt-0.5 text-sm">{{ $selectedPack?->room?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Schedule') }}</dt>
                <dd class="mt-0.5 text-sm">{{ $selectedPack?->scheduleLabel() ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Price') }}</dt>
                <dd class="mt-0.5 text-sm tabular-nums">
                    {{ number_format((float) $selectedPack?->price, 2, ',', ' ') }} €
                    @if ($selectedPack?->is_camp)
                        <span class="text-xs text-subtle">· {{ __('invoiced separately') }}</span>
                    @elseif (! $selectedPack?->allow_discount)
                        <span class="text-xs text-subtle">· {{ __('no family discount') }}</span>
                    @endif
                </dd>
            </div>
        </dl>

        {{-- Sparrings: free practice has no coach to confirm them, so none there. --}}
        @if ($selectedPack && $selectedPack->type !== \App\Domains\Shared\Enums\TrainingType::FREE)
            <div class="mt-4 border-t border-base-300 pt-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-muted">{{ __('Sparrings') }}</span>
                    @forelse ($selectedPack->sparrings as $sparring)
                        <span class="badge badge-soft badge-neutral gap-1" wire:key="pack-sparring-{{ $sparring->id }}">
                            {{ $sparring->full_name }}
                            @if ($this->mayManage)
                                <button type="button" class="cursor-pointer" wire:click="detachPackSparring({{ $sparring->id }})"
                                    aria-label="{{ __('Remove') }}">
                                    <x-icon name="o-x-mark" class="size-3" />
                                </button>
                            @endif
                        </span>
                    @empty
                        <span class="text-sm text-subtle">{{ __('None') }}</span>
                    @endforelse
                    @if ($this->mayManage)
                        <x-button class="btn-ghost btn-xs" icon="o-plus" :label="__('Add a sparring')"
                            wire:click="openPackSparring" />
                    @endif
                </div>
            </div>
        @endif

        @if (filled($selectedPack?->description))
            <p class="mt-4 border-t border-base-300 pt-3 text-sm text-base-content/70">
                <span class="whitespace-pre-line">{{ $selectedPack->description }}</span>
            </p>
        @endif
    </div>

    {{-- ── The four numbers the committee asks for, in the shared stat card. --}}
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-admin.shared.stat-card
            :label="__('Enrolled')"
            :value="$summary['capped'] ? $summary['enrolled'] . ' / ' . $summary['max'] : (string) $summary['enrolled']"
            :hint="$summary['capped']
                ? trans_choice(':n spot left|:n spots left', $summary['spotsLeft'], ['n' => $summary['spotsLeft']])
                : __('No cap on this pack')"
            icon="o-users"
            color="primary" />
        <x-admin.shared.stat-card
            :label="__('Waiting list')"
            :value="(string) $summary['waiting']"
            :hint="$summary['pending'] > 0
                ? trans_choice('{1}:count request to approve|[2,*]:count requests to approve', $summary['pending'], ['count' => $summary['pending']])
                : __('Nothing to approve')"
            icon="o-queue-list"
            :color="$summary['pending'] > 0 ? 'warning' : 'neutral'" />
        <x-admin.shared.stat-card
            :label="__('Sessions')"
            :value="(string) $summary['sessions']"
            :hint="__(':held held · :upcoming to come · :cancelled cancelled', [
                'held' => $summary['held'],
                'upcoming' => $summary['upcoming'],
                'cancelled' => $summary['cancelled'],
            ])"
            icon="o-calendar-days" />
        <x-admin.shared.stat-card
            :label="__('Turnout')"
            :value="$summary['turnout'] !== null ? $summary['turnout'] . '%' : '—'"
            :hint="$summary['turnout'] !== null
                ? __('Of the enrolled members, over the recorded sessions')
                : __('No session recorded yet')"
            icon="o-chart-bar"
            :color="match (true) {
                $summary['turnout'] === null => 'neutral',
                $summary['turnout'] >= 70 => 'success',
                $summary['turnout'] >= 40 => 'warning',
                default => 'error',
            }" />
    </div>

    @php
        $tabs = [
            'roster' => ['label' => __('Members'), 'icon' => 'o-user-group'],
            'sessions' => ['label' => __('Sessions'), 'icon' => 'o-calendar-days'],
            'attendance' => ['label' => __('Attendance'), 'icon' => 'o-check-circle'],
        ];
    @endphp
    <div role="tablist" class="mt-6 mb-4 flex flex-wrap gap-1 border-b border-base-300">
        @foreach ($tabs as $name => $item)
            <button type="button" role="tab" wire:click="$set('tab', '{{ $name }}')"
                aria-selected="{{ $tab === $name ? 'true' : 'false' }}"
                class="-mb-px flex min-h-11 cursor-pointer items-center gap-2 border-b-2 px-4 text-sm {{ $tab === $name ? 'border-primary font-semibold text-primary' : 'border-transparent text-base-content/70' }}">
                <x-icon :name="$item['icon']" class="h-4 w-4" />
                {{ $item['label'] }}
            </button>
        @endforeach
    </div>

    @if ($tab === 'sessions')
        <livewire:pages::club-events.trainings.pack-sessions :pack-id="$packId" :key="'pack-sessions-' . $packId" />
    @elseif ($tab === 'attendance')
        <livewire:pages::club-events.trainings.pack-attendance :pack-id="$packId" :key="'pack-attendance-' . $packId" />
    @else
        <livewire:pages::club-events.trainings.pack-roster :pack-id="$packId" :key="'pack-roster-' . $packId" />
    @endif

    {{-- ── Regular sparring of the pack ─────────────────────────────────────── --}}
    <x-app-modal :title="__('Add a sparring')" wire:model="packSparringModal" separator :open="$packSparringModal">
        <p class="text-sm text-base-content/70">
            {{ __('A regular sparring is expected at every coming session of the pack. The coach confirms their presence when taking attendance.') }}
        </p>

        <x-choices-offline class="mt-4" :label="__('Sparring')" wire:model="packSparringUserId"
            :options="$this->anyMemberOptions" option-label="name" single searchable />

        <x-slot:actions>
            <x-button :label="__('Abandon')" wire:click="$set('packSparringModal', false)" />
            <x-button :label="__('Add')" class="btn-primary" wire:click="attachPackSparring" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- ── Add a member by hand ─────────────────────────────────────────────── --}}
    <x-app-modal :title="__('Add a member to this pack')" wire:model="addMemberModal" separator
        :open="$addMemberModal">
        <p class="text-sm text-base-content/70">
            {{ __('The spot is validated straight away — no request to approve. The member is notified and their balance is updated.') }}
        </p>

        @if ($this->addMemberOverCapacity)
            <x-alert class="alert-warning mt-3" icon="o-exclamation-triangle">
                {{ __('This pack is full. Adding someone takes it over its cap.') }}
            </x-alert>
        @endif

        <x-choices-offline class="mt-4" :label="__('Member')" wire:model="addMemberUserId"
            :options="$this->addMemberOptions" option-label="name" single searchable />

        @if ($selectedPack?->is_camp)
            {{-- Un stage n'a ni pro rata ni remise : une présence partielle est un
                 prix fixé pour ce membre, avec son motif. --}}
            <x-input class="mt-3" type="number" min="0" step="0.50" wire:model="addMemberPrice"
                :label="__('Price for this member (€)')"
                :placeholder="number_format((float) $selectedPack->price, 2, '.', '')"
                :hint="__('Leave empty for the training camp price.')" />
            <x-input class="mt-3" wire:model="addMemberPriceReason" :label="__('Reason')"
                :placeholder="__('E.g. one day only')" />
        @else
        {{-- Seulement sur un pack entamé : avant son début, tout le monde paie
             plein tarif et la case ne changerait rien. --}}
        @if ($this->addMemberPackStarted)
            <x-checkbox class="mt-3" wire:model.live="addMemberWholePack"
                :label="__('Here since the start of the pack')"
                :hint="__('Bills the whole pack, not just the months left.')" />
        @endif

        @unless ($addMemberWholePack && $this->addMemberPackStarted)
            <x-input class="mt-3" type="date" :label="__('Enrolled since')" wire:model="addMemberStartsOn" />

            <p class="mt-1 text-xs text-base-content/50">
                {{ __('Leave empty to start today. Set an earlier date to bill the months already attended.') }}
            </p>
        @endunless

        <div class="mt-4">
            <x-admin.shared.inline-discount :mode="$inlineDiscountMode" />
        </div>
        @endif

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('addMemberModal', false)" />
            <x-button :label="__('Add the member')" class="btn-primary" wire:click="addMemberToPack" spinner />
        </x-slot:actions>
    </x-app-modal>
</div>
