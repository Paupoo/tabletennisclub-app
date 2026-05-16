<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="{{ __('Tournaments') }}">
        <x-slot:actions>
            <x-input
                clearable
                class="input-sm w-48"
                icon="o-magnifying-glass"
                placeholder="{{ __('Search…') }}"
                wire:model.live.debounce.300ms="search"
            />
            <x-button
                class="btn-primary"
                icon="o-plus"
                label="{{ __('Create') }}"
                link="{{ route('admin.tournaments.wizard') }}"
                responsive
            />
        </x-slot:actions>
    </x-header>

    {{-- ── Filter tabs ─────────────────────────────────────────────────────── --}}
    @php
        $tabs = [
            'all'      => ['label' => __('All'),      'icon' => null],
            'live'     => ['label' => __('Live'),     'icon' => 'o-rocket-launch'],
            'upcoming' => ['label' => __('Upcoming'), 'icon' => 'o-calendar'],
            'closed'   => ['label' => __('Closed'),   'icon' => 'o-check-circle'],
            'draft'    => ['label' => __('Draft'),    'icon' => 'o-document'],
        ];
    @endphp

    <div class="mb-6 flex gap-1 overflow-x-auto border-b border-base-200 pb-0">
        @foreach ($tabs as $key => $tabDef)
            <button
                wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'flex items-center gap-1.5 px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors whitespace-nowrap',
                    'border-primary text-primary'                                       => $tab === $key,
                    'border-transparent text-base-content/60 hover:text-base-content'   => $tab !== $key,
                ])
            >
                @if ($tabDef['icon'])
                    <x-icon class="h-3.5 w-3.5" name="{{ $tabDef['icon'] }}" />
                @endif
                {{ $tabDef['label'] }}
                <span @class([
                    'badge badge-sm font-mono tabular-nums',
                    'badge-primary' => $tab === $key,
                    'badge-ghost'   => $tab !== $key,
                ])>{{ $this->counts[$key] }}</span>
            </button>
        @endforeach
    </div>

    {{-- ── Content ──────────────────────────────────────────────────────────── --}}
    @if ($this->tournaments->isEmpty())

        {{-- Empty state --}}
        <div class="flex flex-col items-center justify-center py-24 text-base-content/40">
            <x-icon class="mb-4 h-14 w-14" name="o-trophy" />
            <p class="text-base font-semibold">
                @if ($search)
                    {{ __('No tournament matches ":search"', ['search' => $search]) }}
                @else
                    {{ __('No tournaments yet') }}
                @endif
            </p>
            @if (! $search)
                <p class="mt-1 text-sm">
                    <a class="link link-primary" href="{{ route('admin.tournaments.wizard') }}">{{ __('Create your first tournament') }}</a>
                </p>
            @endif
        </div>

    @elseif ($tab === 'all' && ! $search)

        {{-- Grouped display --}}
        @php
            $groups = [
                'live'     => ['label' => __('Live'),     'icon' => 'o-rocket-launch', 'class' => 'text-primary',          'statuses' => ['pending']],
                'upcoming' => ['label' => __('Upcoming'), 'icon' => 'o-calendar',      'class' => 'text-info',             'statuses' => ['published', 'locked', 'setup']],
                'draft'    => ['label' => __('Draft'),    'icon' => 'o-document',      'class' => 'text-base-content/50',  'statuses' => ['draft']],
                'closed'   => ['label' => __('Closed'),  'icon' => 'o-check-circle',  'class' => 'text-base-content/40',  'statuses' => ['closed', 'cancelled']],
            ];
        @endphp

        <div class="space-y-10">
            @foreach ($groups as $group)
                @php
                    $items = $this->tournaments->filter(fn ($t) => in_array($t->status->value, $group['statuses']));
                @endphp

                @if ($items->isNotEmpty())
                    <section>
                        <div class="mb-4 flex items-center gap-2">
                            <x-icon class="h-4 w-4 {{ $group['class'] }}" name="{{ $group['icon'] }}" />
                            <h2 class="text-xs font-bold uppercase tracking-widest {{ $group['class'] }}">
                                {{ $group['label'] }}
                            </h2>
                            <span class="badge badge-ghost badge-sm">{{ $items->count() }}</span>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($items as $tournament)
                                <x-admin.club-events.tournaments.tournament-card :tournament="$tournament" />
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
        </div>

    @else

        {{-- Flat list (filtered) --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->tournaments as $tournament)
                <x-admin.club-events.tournaments.tournament-card :tournament="$tournament" />
            @endforeach
        </div>

    @endif
</div>
