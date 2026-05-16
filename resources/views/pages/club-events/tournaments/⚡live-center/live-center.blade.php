<div class="p-4 max-w-6xl mx-auto">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header title="{{ $tournament->name }}" subtitle="{{ __('Live tournament management') }}">
        <x-slot:actions>
            @if ($this->tournamentClosed)
                <x-badge value="{{ __('Closed') }}" class="badge-neutral" icon="o-lock-closed" />
            @elseif ($this->poolsPhaseComplete && ! $this->bracketExists)
                <x-button label="{{ __('Create bracket') }}" icon="o-trophy"
                    class="btn-warning btn-sm animate-pulse"
                    wire:click="generateBracket" spinner="generateBracket" />
            @endif
        </x-slot:actions>
    </x-header>

    {{-- ── Metro line : phase indicator ─────────────────────────────── --}}
    <div class="flex items-center gap-0 mb-8 mt-2 select-none overflow-x-auto">

        @php
            $phases = [
                ['label' => __('Pools'),   'done' => $this->poolsPhaseComplete,   'active' => ! $this->poolsPhaseComplete],
                ['label' => __('Bracket'), 'done' => $this->bracketPhaseComplete, 'active' => $this->poolsPhaseComplete && ! $this->bracketPhaseComplete],
            ];
        @endphp

        @foreach ($phases as $i => $phase)
            <div class="flex flex-col items-center gap-1 shrink-0">
                <div @class([
                    'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all',
                    'bg-success border-success text-success-content'                              => $phase['done'],
                    'bg-primary border-primary text-primary-content ring-4 ring-primary/20'       => $phase['active'] && ! $phase['done'],
                    'bg-base-200 border-base-300 text-base-content/30'                           => ! $phase['done'] && ! $phase['active'],
                ])>
                    @if ($phase['done'])
                        <x-icon name="o-check" class="w-4 h-4" />
                    @elseif ($phase['active'])
                        <span class="loading loading-ring loading-xs"></span>
                    @else
                        <span class="text-xs font-bold">{{ $i + 1 }}</span>
                    @endif
                </div>
                <span @class([
                    'text-xs font-semibold whitespace-nowrap',
                    'text-success'         => $phase['done'],
                    'text-primary'         => $phase['active'] && ! $phase['done'],
                    'text-base-content/30' => ! $phase['done'] && ! $phase['active'],
                ])>{{ $phase['label'] }}</span>
            </div>

            @if (! $loop->last)
                <div @class([
                    'flex-1 h-0.5 mx-2 min-w-[40px] transition-all',
                    'bg-success'  => $phases[$i + 1]['done'] || $phases[$i + 1]['active'],
                    'bg-base-300' => ! $phases[$i + 1]['done'] && ! $phases[$i + 1]['active'],
                ])></div>
            @endif
        @endforeach
    </div>

    {{-- ── Tabs ────────────────────────────────────────────────────── --}}
    <x-tabs wire:model="activeTab" class="mb-6">

        <x-tab name="pools" icon="o-user-group">
            <x-slot:label>{{ __('Pools') }}</x-slot:label>
            @include('admin.club-events.tournaments.partials.live.tabs.pools')
        </x-tab>

        <x-tab name="tables" icon="o-squares-2x2">
            <x-slot:label>
                {{ __('Tables') }}
                @php $inProgress = $this->tables->flatten(1)->where('is_free', false)->count(); @endphp
                @if ($inProgress > 0)
                    <x-badge value="{{ $inProgress }}" class="ml-1 badge-primary badge-sm" />
                @endif
            </x-slot:label>
            @include('admin.club-events.tournaments.partials.live.tabs.tables')
        </x-tab>

        <x-tab name="upcoming" icon="o-megaphone">
            <x-slot:label>
                {{ __('Upcoming') }}
                @php $upcomingCount = $this->upcomingMatches->count(); @endphp
                @if ($upcomingCount > 0)
                    <x-badge value="{{ $upcomingCount }}" class="ml-1 badge-ghost badge-sm" />
                @endif
            </x-slot:label>
            @include('admin.club-events.tournaments.partials.live.tabs.upcoming')
        </x-tab>

        <x-tab name="bracket" icon="o-trophy">
            <x-slot:label>{{ __('Bracket') }}</x-slot:label>
            @include('admin.club-events.tournaments.partials.live.tabs.bracket')
        </x-tab>

        <x-tab name="rankings" icon="o-chart-bar">
            <x-slot:label>{{ __('Rankings') }}</x-slot:label>
            @include('admin.club-events.tournaments.partials.live.tabs.rankings')
        </x-tab>

        <x-tab name="closure" icon="o-lock-closed">
            <x-slot:label>
                {{ __('Closure') }}
                @if ($this->bracketPhaseComplete && ! $this->tournamentClosed)
                    <x-badge value="!" class="ml-1 badge-error badge-xs" />
                @elseif ($this->tournamentClosed)
                    <x-icon name="o-check-circle" class="ml-1 w-3.5 h-3.5 text-success inline" />
                @endif
            </x-slot:label>
            @include('admin.club-events.tournaments.partials.live.tabs.closure')
        </x-tab>

    </x-tabs>

    {{-- ── Drawers ──────────────────────────────────────────────────── --}}
    @include('admin.club-events.tournaments.partials.live.drawers.score-entry')
    @include('admin.club-events.tournaments.partials.live.drawers.launch-match')

</div>
