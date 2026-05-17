<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <x-header progress-indicator separator subtitle="{{ __('Past, current and upcoming seasons') }}"
        title="{{ __('Seasons') }}">
        <x-slot:actions>
            <x-button class="btn-ghost btn-sm" icon="o-sparkles" label="{{ __('Auto-provision') }}"
                tooltip="{{ __('Ensure the next 2 seasons exist') }}" wire:click="openProvision" />
            @can('create', \App\Models\ClubEvents\Interclub\Season::class)
                <x-button class="btn-primary btn-sm" icon="o-plus" label="{{ __('New season') }}"
                    wire:click="openCreate" />
            @endcan
        </x-slot:actions>
    </x-header>

    {{-- ── Season list ─────────────────────────────────────────────────────── --}}
    <div class="mt-2 space-y-3">
        @if (! $showAllPastSeasons && $hiddenPastCount > 0)
            <button class="w-full py-2 text-center text-sm text-base-content/40 transition hover:text-base-content/70"
                wire:click="$set('showAllPastSeasons', true)">
                {{ __(':count older seasons hidden — click to show all', ['count' => $hiddenPastCount]) }}
            </button>
        @endif

        @forelse ($seasons as $season)
            @php
                $isPast = $season->isPast();
                $isFuture = $season->isFuture();
                $isCurrent = $season->isCurrent();
            @endphp

            <div wire:key="{{ $season->id }}" @class([
                'flex items-center justify-between rounded-xl border px-5 py-4 transition',
                'border-primary/30 bg-primary/5' => $isCurrent,
                'border-base-200 bg-base-100 opacity-60' => $isPast,
                'border-base-200 bg-base-100' => $isFuture,
            ])>
                {{-- Left: name + dates --}}
                <div class="flex items-center gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span @class([
                                'text-base font-semibold',
                                'text-primary' => $isCurrent,
                                'text-base-content' => ! $isCurrent,
                            ])>{{ $season->name }}</span>

                            @if ($isCurrent)
                                <x-badge class="badge-primary badge-soft" value="{{ __('Active') }}" />
                            @elseif ($isPast)
                                <x-badge class="badge-ghost" value="{{ __('Past') }}" />
                            @else
                                <x-badge class="badge-info badge-soft" value="{{ __('Upcoming') }}" />
                            @endif
                        </div>

                        <p class="mt-0.5 text-xs text-base-content/50">
                            {{ $season->start_at->translatedFormat('d M Y') }}
                            →
                            {{ $season->end_at->translatedFormat('d M Y') }}
                        </p>
                    </div>
                </div>

                {{-- Right: actions --}}
                <div class="flex items-center gap-2">
                    @if (! $isCurrent)
                        <x-button class="btn-ghost btn-sm"
                            icon="{{ $isPast ? 'o-arrow-uturn-left' : 'o-bolt' }}"
                            label="{{ __('Set active') }}"
                            tooltip="{{ $isPast ? __('Re-activate this past season') : __('Activate this upcoming season') }}"
                            wire:click="openActivate({{ $season->id }})" />
                    @endif

                    @can('update', $season)
                        <x-button class="btn-ghost btn-sm" icon="o-pencil" wire:click="openEdit({{ $season->id }})" />
                    @endcan
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-base-300 py-16 text-center text-base-content/40">
                <x-icon class="mx-auto mb-2 h-10 w-10" name="o-calendar" />
                <p>{{ __('No seasons yet.') }}</p>
                <x-button class="btn-primary mt-4" label="{{ __('Create first season') }}"
                    wire:click="openCreate" />
            </div>
        @endforelse

        @if ($showAllPastSeasons && $pastCount > 1)
            <button class="w-full py-2 text-center text-sm text-base-content/40 transition hover:text-base-content/70"
                wire:click="$set('showAllPastSeasons', false)">
                {{ __('Collapse past seasons') }}
            </button>
        @endif
    </div>

    {{-- ── Auto-provision info ─────────────────────────────────────────────── --}}
    <div class="mt-6 rounded-xl border border-base-200 bg-base-100 p-4 text-sm text-base-content/60">
        <div class="flex items-start gap-3">
            <x-icon class="mt-0.5 h-4 w-4 shrink-0 text-info" name="o-information-circle" />
            <p>
                {{ __('Every year on July 1st, the application automatically provisions the next two upcoming seasons (September → June). Use "Auto-provision" to trigger this manually, or "New season" to create a custom one.') }}
            </p>
        </div>
    </div>

    {{-- ================================================================
         PROVISION MODAL
    ================================================================ --}}
    <x-modal title="{{ __('Auto-provision seasons') }}" wire:model="provisionModal" separator>
        <div class="space-y-3 text-sm text-base-content/70">
            <p>{{ __('This will create the next two seasons after the current active season, if they do not already exist.') }}</p>
            <p>{{ __('Each season runs from September 1st to June 30th. Already-existing seasons and overlapping date ranges are automatically skipped.') }}</p>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('provisionModal', false)" />
            <x-button class="btn-primary" icon="o-sparkles" label="{{ __('Provision') }}"
                wire:click="confirmProvision" />
        </x-slot:actions>
    </x-modal>

    {{-- ================================================================
         ACTIVATE MODAL
    ================================================================ --}}
    <x-modal title="{{ __('Activate season') }}" wire:model="activateModal" separator>
        <div class="space-y-4">
            <p class="text-base-content/70">
                {{ __('You are about to make') }}
                <span class="font-semibold text-base-content">{{ $activateName }}</span>
                {{ __('the active season.') }}
            </p>
            <x-alert class="alert-warning" icon="o-exclamation-triangle"
                title="{{ __('The current active season will be deactivated. This affects all season-scoped data (registrations, training packs, etc.).') }}" />
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('activateModal', false)" />
            <x-button class="btn-warning" icon="o-bolt" label="{{ __('Activate') }}"
                wire:click="confirmActivate" />
        </x-slot:actions>
    </x-modal>

    {{-- ================================================================
         EDIT MODAL
    ================================================================ --}}
    <x-modal title="{{ __('Edit season') }}" wire:model="editModal" separator>
        <div class="space-y-4">
            <x-input label="{{ __('Name') }}" placeholder="{{ __('E.g. 2026-2027') }}"
                wire:model="editName" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="{{ __('Start date') }}" type="date" wire:model="editStartAt" />
                <x-input label="{{ __('End date') }}" type="date" wire:model="editEndAt" />
            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('editModal', false)" />
            <x-button class="btn-primary" icon="o-check" label="{{ __('Save') }}"
                wire:click="updateSeason" />
        </x-slot:actions>
    </x-modal>

    {{-- ================================================================
         CREATE MODAL
    ================================================================ --}}
    <x-modal title="{{ __('New season') }}" wire:model="createModal" separator>
        <div class="space-y-4">
            <x-input label="{{ __('Name') }}" placeholder="{{ __('E.g. 2026-2027') }}"
                wire:model="createName" />
            <div class="grid grid-cols-2 gap-4">
                <x-input label="{{ __('Start date') }}" type="date" wire:model="createStartAt" />
                <x-input label="{{ __('End date') }}" type="date" wire:model="createEndAt" />
            </div>
        </div>

        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('createModal', false)" />
            <x-button class="btn-primary" icon="o-calendar" label="{{ __('Create') }}"
                wire:click="createSeason" />
        </x-slot:actions>
    </x-modal>
</div>
