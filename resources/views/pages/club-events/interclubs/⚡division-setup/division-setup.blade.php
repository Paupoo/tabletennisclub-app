<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator :title="__('Division Setup')">
        <x-slot:subtitle>{{ __('Define the opponent teams for each division, once per season.') }}</x-slot:subtitle>
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- ── Active filter chips ──────────────────────────────────────────────── --}}
    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── Filter drawer ────────────────────────────────────────────────────── --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-muted">
                    {{ __('Season') }}
                </p>
                <x-select
                    :options="$seasons"
                    option-label="name"
                    option-value="id"
                    wire:model.live="seasonId"
                    :placeholder="__('Select a season')"
                    class="w-full" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    @if (! $seasonId)
        <x-card class="mt-4">
            <p class="py-12 text-center text-sm text-gray-500">{{ __('Select a season to manage divisions.') }}</p>
        </x-card>
    @elseif ($leagues->isEmpty())
        <x-card class="mt-4">
            <p class="py-12 text-center text-sm text-gray-500">{{ __('No divisions found for this season. Create your club\'s teams first.') }}</p>
        </x-card>
    @else
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Left panel: division list grouped by category then level --}}
            <div class="space-y-4">
                @php
                    $categoryOrder  = ['MEN' => 1, 'VETERANS' => 2, 'WOMEN' => 3];
                    // Belgian hierarchy: National (top) → Regional → Provincial
                    $levelOrder     = ['NATIONAL' => 1, 'REGIONAL' => 2, 'PROVINCIAL_BW' => 3];
                    $levelLabels    = ['NATIONAL' => 'National', 'REGIONAL' => __('Regional'), 'PROVINCIAL_BW' => 'Provincial BW'];
                    // Within a level, sort by our team's letter (A first)
                    $leagueSortKey  = fn ($league) => $league->teams
                        ->filter(fn ($t) => $t->club?->is_own_club)
                        ->pluck('name')
                        ->sort()
                        ->first() ?? 'ZZZ';
                    $groupedLeagues = $leagues
                        ->groupBy('category')
                        ->sortBy(fn ($_, $cat) => $categoryOrder[$cat] ?? 99);
                @endphp

                @php
                    $catColor = ['MEN' => 'blue', 'VETERANS' => 'amber', 'WOMEN' => 'pink'];
                @endphp
                @foreach ($groupedLeagues as $category => $categoryLeagues)
                    @php $meta = $categoryMeta[$category] ?? ['bg' => 'bg-gray-50', 'border' => 'border-base-300', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'label' => $category]; @endphp

                    <x-section-accordion
                        :label="$meta['label']"
                        :count="(string) $categoryLeagues->count()"
                        :color="$catColor[$category] ?? 'gray'">
                        {{-- Division list grouped by level --}}
                            @php
                                $byLevel           = $categoryLeagues
                                    ->groupBy('level')
                                    ->sortBy(fn ($_, $lvl) => $levelOrder[$lvl] ?? 99);
                                $hasMultipleLevels = $byLevel->count() > 1;
                            @endphp

                            <div class="space-y-3">
                                @foreach ($byLevel as $level => $levelLeagues)
                                    <div class="space-y-1.5">
                                        @if ($hasMultipleLevels)
                                            <p class="px-1 text-xs font-semibold uppercase tracking-widest {{ $meta['text'] }}">
                                                {{ $levelLabels[$level] ?? $level }}
                                            </p>
                                        @endif

                                        @foreach ($levelLeagues->sortBy($leagueSortKey) as $league)
                                            @php
                                                $count          = $league->teams->filter(fn ($t) => ! $t->club?->is_own_club)->count();
                                                $active         = $selectedLeagueId === $league->id;
                                                $ourTeamLetters = $league->teams
                                                    ->filter(fn ($t) => $t->club?->is_own_club)
                                                    ->pluck('name')
                                                    ->sort()
                                                    ->implode(', ');
                                            @endphp
                                            <button
                                                type="button"
                                                wire:click="selectLeague({{ $league->id }})"
                                                class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors
                                                    {{ $active ? $meta['bg'] . ' ' . $meta['border'] : 'border-base-300 bg-base-100 hover:bg-base-200' }}"
                                            >
                                                <span class="h-2 w-2 shrink-0 rounded-full {{ $meta['dot'] }}"></span>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-semibold {{ $active ? $meta['text'] : '' }}">
                                                        <span class="mr-1 text-xs font-normal text-muted">{{ __('Division') }}</span>{{ $league->division }}
                                                    </p>
                                                    @if ($ourTeamLetters)
                                                        <p class="text-xs text-muted">{{ __('Team') }} {{ $ourTeamLetters }}</p>
                                                    @endif
                                                </div>
                                                <span class="text-xs font-medium {{ $active ? $meta['text'] : 'text-gray-400' }}">{{ $count }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                    </x-section-accordion>
                @endforeach
            </div>

            {{-- Right panel: participants for selected division --}}
            <div class="lg:col-span-2">
                @if (! $selectedLeagueId)
                    <div class="flex h-48 items-center justify-center rounded-xl border border-dashed border-base-300">
                        <p class="text-sm text-gray-400">{{ __('Select a division to manage its participants.') }}</p>
                    </div>
                @else
                    @php
                        $selectedLeague = $leagues->firstWhere('id', $selectedLeagueId);
                        $meta = $categoryMeta[$selectedLeague?->category ?? ''] ?? ['bg' => 'bg-gray-50', 'border' => 'border-base-300', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'label' => ''];
                    @endphp
                    <div class="mb-4 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full {{ $meta['bg'] }} {{ $meta['border'] }} border px-4 py-1.5">
                                <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>
                                <span class="text-sm font-bold {{ $meta['text'] }} uppercase tracking-wide">{{ $selectedLeague?->division }}</span>
                                <span class="text-xs {{ $meta['text'] }} opacity-60">{{ $meta['label'] }}</span>
                            </span>
                            <span class="text-sm text-gray-400">
                                {{ $participants->count() }} {{ __('opponent(s)') }}
                            </span>
                        </div>
                        <x-button
                            class="btn-primary btn-sm"
                            icon="o-plus"
                            :label="__('Add opponent')"
                            wire:click="openAddModal" />
                    </div>

                    @if ($participants->isEmpty())
                        <div class="flex h-40 items-center justify-center rounded-xl border border-dashed border-base-300">
                            <div class="text-center">
                                <p class="text-sm text-gray-400">{{ __('No opponents yet for this division.') }}</p>
                                <x-button class="btn-primary btn-sm mt-3" icon="o-plus" :label="__('Add first opponent')" wire:click="openAddModal" />
                            </div>
                        </div>
                    @else
                        <x-card class="overflow-hidden p-0">
                            <div class="divide-base-200 divide-y">
                                @foreach ($participants as $team)
                                    <div class="hover:bg-base-50 flex items-center gap-4 px-4 py-3 transition-colors" wire:key="participant-{{ $team->id }}">
                                        <div class="bg-base-200 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-sm font-bold">
                                            {{ $team->name }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-semibold">{{ $team->club?->name ?? '—' }}</p>
                                            @if ($team->club?->street)
                                                <p class="text-base-content/50 text-xs">{{ $team->club->street }}</p>
                                            @endif
                                        </div>
                                        <x-button
                                            class="btn-ghost btn-sm btn-circle text-error"
                                            icon="o-trash"
                                            :tooltip="__('Remove')"
                                            wire:click="confirmDelete({{ $team->id }})" :aria-label="__('Remove')" />
                                    </div>
                                @endforeach
                            </div>
                        </x-card>
                    @endif
                @endif
            </div>
        </div>
    @endif

    {{-- Modal add participant --}}
    <x-app-modal wire:model="addModal" :title="__('Add opponent')" separator :open="$addModal">
        <div class="space-y-4">
            <x-input
                :label="__('Club name')"
                wire:model="formClubName"
                placeholder="ex: TT Wavre"
                icon="o-building-office" />
            <x-input
                :label="__('Address (optional)')"
                wire:model="formClubStreet"
                placeholder="ex: Rue de la Gare 10, 1300 Wavre"
                icon="o-map-pin" />
            <x-input
                :label="__('Team letter')"
                wire:model="formTeamLetter"
                placeholder="A"
                icon="o-tag"
                class="max-w-25" />
        </div>
        <x-slot:actions>
            <x-button :label="__('Cancel')" wire:click="$set('addModal', false)" />
            <x-button class="btn-primary" :label="__('Add')" wire:click="addParticipant" spinner />
        </x-slot:actions>
    </x-app-modal>

    {{-- Modal delete --}}
    <x-confirm-modal model="deleteModal" :title="__('Remove participant')" :subtitle="__('Warning!')"
        :confirmLabel="__('Remove')" confirmAction="deleteParticipant" :open="$deleteModal">
        <p>{{ __('Are you sure you want to remove this opponent from the division?') }}</p>
    </x-confirm-modal>
</div>
