<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    {{-- PACK LIST HEADER --}}
    <x-header progress-indicator separator :subtitle="$viewSeason?->name ?? __('Select a season')"
        :title="__('Trainings')">
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
            @if ($this->mayExportRoster && $viewSeason)
                <x-dropdown :id="'roster-export'" right>
                    <x-slot:trigger>
                        <x-button class="btn-ghost btn-sm" icon="o-arrow-down-tray" :label="__('Export the enrolled')" />
                    </x-slot:trigger>
                    <x-menu-item :title="__('Excel (.xlsx)')" wire:click="exportRoster('xlsx')" />
                    <x-menu-item :title="__('CSV')" wire:click="exportRoster('csv')" />
                </x-dropdown>
            @endif
            @if ($this->mayManage)
                <x-button class="btn-ghost btn-sm" icon="o-swatch" :label="__('Levels')"
                    wire:click="$set('levelDrawer', true)" />
                <x-button class="btn-primary btn-sm" icon="o-plus" :label="__('New pack')" :link="route('admin.trainings.create')" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Season: navigation, not a filter (DS-A) ──────────────────────── --}}
    <x-admin.shared.season-nav model="viewSeasonId" :options="$seasonOptions" class="mt-4" />

    {{-- ── Active filter chips ──────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Filter drawer ──────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Availability') }}
                </p>
                <label class="flex items-center gap-2 cursor-pointer">
                    <x-checkbox wire:model.live="showInactive" />
                    <span class="text-sm">{{ __('Show withdrawn packs') }}</span>
                </label>
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── Season guard ────────────────────────────────────────────────────── --}}
    @if (! $viewSeason)
    @if (empty($seasonOptions))
        <x-admin.shared.missing-season-state
            :message="__('Training packs belong to a season. Open one to build this year\'s offer.')" />
    @else
        <x-alert class="alert-info" icon="o-information-circle"
            :title="__('Select a season above to view training packs.')" />
    @endif
    @else
    {{-- ================================================================
         PACK LIST — grouped by level
    ================================================================ --}}
    @php
        $grouped = $packs->groupBy(fn ($p) => $p->level?->label ?? __('No level'));
        $levelColor = [
            'Beginners'       => 'emerald',
            'Elite'           => 'violet',
            'Intermediate'    => 'blue',
            'Kids'            => 'pink',
            'Open'            => 'gray',
            'Young potential' => 'amber',
        ];
    @endphp

    @if ($grouped->isEmpty())
        <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
            <x-icon class="mx-auto mb-2 h-10 w-10" name="o-academic-cap" />
            <p>{{ __('No training packs for this season yet.') }}</p>
            @if ($this->mayManage)
                <x-button class="btn-primary mt-4" :label="__('Create first pack')"
                    :link="route('admin.trainings.create')" />
            @endif
        </div>
    @else
        <div class="space-y-6">
            @foreach ($grouped as $level => $items)
                <x-section-accordion
                    label="{{ $level }}"
                    :count="$items->count() . ' ' . __('pack(s)')"
                    :color="$levelColor[$level] ?? 'gray'">
                    <div class="grid gap-4 pb-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($items as $pack)
                            @php
                                // Un pack sans plafond n'a pas de places à compter : ni
                                // celui du pack, ni celui de la salle, que le libre-service ignore.
                                $capped = ! $pack->is_open_enrollment && $pack->effectiveMaxParticipants() > 0;
                                $enrolled = $pack->enrolledCount();
                                $max = $pack->effectiveMaxParticipants();
                                $full = $max > 0 && $enrolled >= $max;
                            @endphp
                            {{-- No overflow-hidden: it would clip the actions dropdown at the
                                 bottom of the card. The header rounds its own corners instead. --}}
                            <div
                                class="group flex flex-col rounded-xl border border-base-300 bg-base-100">
                                {{-- Card header --}}
                                <div class="rounded-t-xl bg-primary/5 px-4 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="font-semibold leading-tight text-primary">
                                                {{ $pack->name }}
                                            </p>
                                            <p class="mt-0.5 text-xs text-primary/60">
                                                {{ $pack->type?->label() }}
                                                @if ($pack->day_of_week)
                                                    · {{ ['', __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')][$pack->day_of_week] }}
                                                    {{ $pack->start_time ? \Carbon\Carbon::parse($pack->start_time)->format('H:i') : '' }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            @if ($pack->is_camp)
                                                <x-badge value="{{ __('Training camp') }}"
                                                    class="badge-primary badge-soft badge-xs" icon="o-sun" />
                                            @endif
                                            @unless ($pack->is_active)
                                                <x-badge value="{{ __('Withdrawn') }}"
                                                    class="badge-warning badge-soft badge-xs" icon="o-eye-slash" />
                                            @endunless
                                            {{-- Closed is the exception worth spotting while scanning the list;
                                                 a withdrawn pack already says more with its own badge. --}}
                                            @if ($pack->is_active && ! $pack->enrollments_open)
                                                <x-badge value="{{ __('Closed to enrolments') }}"
                                                    class="badge-neutral badge-soft badge-xs" icon="o-lock-closed" />
                                            @endif
                                            <x-badge value="{{ number_format($pack->price, 0) }}€"
                                                class="badge-primary badge-soft" />
                                            @if ($pack->eventPost?->status->value === 'PUBLISHED')
                                                <x-badge value="{{ __('On website') }}"
                                                    class="badge-success badge-soft badge-xs" icon="o-globe-alt" />
                                            @elseif ($pack->eventPost)
                                                <x-badge value="{{ __('Draft') }}"
                                                    class="badge-warning badge-soft badge-xs" icon="o-document-text" />
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                {{-- Card body --}}
                                <div class="flex flex-1 flex-col px-4 py-3">
                                    <div class="mb-3 space-y-1 text-sm text-base-content/70">
                                        <div class="flex items-center gap-2">
                                            <x-icon class="h-4 w-4 shrink-0 opacity-40" name="o-user" />
                                            {{ $pack->trainer?->full_name ?? __('No coach') }}
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <x-icon class="h-4 w-4 shrink-0 opacity-40" name="o-map-pin" />
                                            {{ $pack->room?->name ?? '—' }}
                                        </div>
                                    </div>

                                    {{-- Capacity — only where there is a cap to count against.
                                         effectiveMaxParticipants() falls back to the room, so an
                                         open-enrolment pack was showing a limit hasAvailableSpot()
                                         never applies. --}}
                                    @if ($capped)
                                        <div class="mb-3">
                                            <div class="mb-1 flex justify-between text-xs text-base-content/50">
                                                <span>{{ $enrolled }} / {{ $max }} {{ __('enrolled') }}</span>
                                                @if ($full)
                                                    <span class="font-medium text-error">{{ __('Full') }}</span>
                                                @endif
                                            </div>
                                            <progress
                                                class="progress {{ $full ? 'progress-error' : 'progress-primary' }} h-1"
                                                max="{{ $max }}" value="{{ $enrolled }}"></progress>
                                        </div>
                                    @endif

                                    {{-- Actions --}}
                                    <div class="mt-auto flex flex-nowrap items-center gap-1 border-t border-base-300 pt-2">
                                        <x-button class="btn-ghost btn-sm min-w-0 flex-1 text-xs"
                                            icon="o-eye" :label="__('Details')"
                                            :link="route('admin.trainings.show', $pack)" />
                                        @if ($this->mayManage)
                                        <x-button class="btn-ghost btn-sm shrink-0 text-xs" icon="o-pencil"
                                            :aria-label="__('Edit')"
                                            :link="route('admin.trainings.edit', $pack)" />
                                        <livewire:admin.shared.event-post-button
                                            :model="$pack"
                                            event-type="TRAINING"
                                            icon="🎯"
                                            :event-date="$pack->pack_start_date?->toDateString()"
                                            :start-time="$pack->start_time ? \Carbon\Carbon::parse($pack->start_time)->format('H:i:s') : null"
                                            :end-time="$pack->start_time && $pack->duration_minutes ? \Carbon\Carbon::parse($pack->start_time)->addMinutes($pack->duration_minutes)->format('H:i:s') : null"
                                            :price="(string) $pack->price"
                                            :max-participants="$pack->max_participants"
                                            :default-title="$pack->name"
                                            :can-publish="true"
                                            wire:key="ep-training-{{ $pack->id }}"
                                            @event-post-saved.window="$wire.refreshPacks()" />
                                        {{-- Icon-only trigger on purpose: four cards fit across an xl
                                             screen, and a labelled dropdown wraps its text and bursts out
                                             of the card. The labels live on the menu items instead, which
                                             also reads better for actions that cancel sessions and move
                                             money. --}}
                                        {{-- The id is not decoration: mary derives the dropdown's
                                             wire:key from the serialized component, so identical
                                             triggers across cards would collide and confuse Livewire's
                                             DOM diffing. --}}
                                        <x-dropdown class="shrink-0" :id="'pack-actions-' . $pack->id" right
                                            no-x-anchor>
                                            <x-slot:trigger>
                                                <x-button class="btn-ghost btn-sm shrink-0 text-xs"
                                                    :id="'pack-actions-trigger-' . $pack->id"
                                                    icon="o-ellipsis-vertical"
                                                    :aria-label="__('More actions')" />
                                            </x-slot:trigger>

                                            <x-menu-item
                                                :icon="$pack->enrollments_open ? 'o-lock-closed' : 'o-lock-open'"
                                                :title="$pack->enrollments_open ? __('Close enrolments') : __('Reopen enrolments')"
                                                wire:click="toggleEnrollments({{ $pack->id }})" />

                                            @if ($pack->is_active)
                                                <x-menu-item icon="o-eye-slash" separator
                                                    :title="__('Withdraw from the offer')"
                                                    wire:click="openWithdrawPack({{ $pack->id }})" />
                                                <x-menu-item class="text-error" icon="o-x-circle" separator
                                                    :title="__('Stop the pack (cancels and refunds)')"
                                                    wire:click="openDiscontinuePack({{ $pack->id }})" />
                                            @else
                                                <x-menu-item class="text-success" icon="o-arrow-uturn-left"
                                                    :title="__('Put back in the offer')"
                                                    wire:click="restorePack({{ $pack->id }})" />
                                            @endif
                                        </x-dropdown>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-section-accordion>
            @endforeach
        </div>
    @endif
    @endif

    <x-confirm-modal model="withdrawPackModal" :title="__('Withdraw this pack from the offer?')"
        :confirmLabel="__('Withdraw')" confirmClass="btn-warning" confirmAction="confirmWithdrawPack" :open="$withdrawPackModal">
        <p>{{ __('Members will no longer be able to enrol in this pack.') }}</p>
        <p class="mt-2 text-sm opacity-70">
            {{ __('The sessions still take place and the members already enrolled keep their spot — they are not notified. Use « Stop the pack » if the training will not happen at all.') }}
        </p>
        <p class="mt-2 text-sm opacity-70">
            {{ __('Reversible: tick « Show withdrawn packs » to find it and put it back.') }}
        </p>
    </x-confirm-modal>

    <x-app-modal wire:model="discontinuePackModal" :title="__('Stop this pack?')" separator :open="$discontinuePackModal">
        <p>{{ __('The remaining sessions are cancelled and the members are told the training will not happen.') }}</p>

        <div class="p-3 mt-3 text-sm rounded-lg bg-error/10">
            <p>{{ __(':count session(s) still to come will be cancelled.', ['count' => $discontinueImpact['sessions']]) }}</p>
            <p>{{ __(':count member(s) enrolled will be notified and refunded what they overpaid.', ['count' => $discontinueImpact['members']]) }}</p>
            @if ($discontinueImpact['waiting'] > 0)
                <p>{{ __(':count member(s) on the waiting list will be told no spot will open.', ['count' => $discontinueImpact['waiting']]) }}</p>
            @endif
        </div>

        <p class="mt-3 text-sm font-semibold text-error">
            {{ __('This cannot be undone: the emails go out and the refunds enter the treasury workflow.') }}
        </p>

        <x-textarea :label="__('Reason (optional — included in the email)')" wire:model="discontinueReason"
            rows="2" class="mt-3" />

        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('discontinuePackModal', false)" />
            <x-button :label="__('Stop the pack')" class="btn-error" wire:click="confirmDiscontinuePack" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- ── Training levels ──────────────────────────────────────────────────── --}}
    <x-drawer wire:model="levelDrawer" :title="__('Training levels')" right separator with-close-button
        class="w-11/12 lg:w-1/3">
        <p class="mb-4 text-sm text-base-content/60">
            {{ __('A level used by a pack or a session cannot be deleted — retire it instead, and the packs that carry it keep their label.') }}
        </p>

        @foreach ($this->levels as $level)
            <div class="mb-2 flex items-center gap-3 rounded-xl border border-base-300 px-3 py-2">
                <span class="h-3 w-3 shrink-0 rounded-full bg-{{ $level->color }}"></span>

                <span @class(['flex-1 text-sm', 'text-base-content/40 line-through' => ! $level->is_active])>
                    {{ $level->label }}
                </span>

                <x-button class="btn-ghost btn-xs" icon="o-pencil" :aria-label="__('Rename')"
                    wire:click="editLevel({{ $level->id }})" />

                <x-button class="btn-ghost btn-xs"
                    :icon="$level->is_active ? 'o-eye-slash' : 'o-eye'"
                    :aria-label="$level->is_active ? __('Retire') : __('Bring back')"
                    wire:click="toggleLevel({{ $level->id }})" />

                <x-button class="btn-ghost btn-xs text-error" icon="o-trash" :aria-label="__('Delete')"
                    wire:click="deleteLevel({{ $level->id }})" />
            </div>
        @endforeach

        <div class="mt-6 rounded-xl border border-base-300 p-4">
            <p class="mb-3 text-xs font-bold uppercase tracking-wide text-base-content/50">
                {{ ($levelForm['id'] ?? null) ? __('Rename the level') : __('New level') }}
            </p>

            <x-input :label="__('Label')" wire:model="levelForm.label" :placeholder="__('E.g. Veterans')" />

            <x-select class="mt-3" :label="__('Colour')" wire:model="levelForm.color" :options="[
                ['id' => 'primary', 'name' => __('Blue')],
                ['id' => 'success', 'name' => __('Green')],
                ['id' => 'warning', 'name' => __('Amber')],
                ['id' => 'error', 'name' => __('Red')],
                ['id' => 'info', 'name' => __('Sky')],
            ]" />

            <div class="mt-4 flex gap-2">
                <x-button class="btn-primary btn-sm" :label="__('Save')" wire:click="saveLevel" spinner />
                @if ($levelForm['id'] ?? null)
                    <x-button class="btn-ghost btn-sm" :label="__('Cancel')" wire:click="newLevel" />
                @endif
            </div>
        </div>
    </x-drawer>
</div>
