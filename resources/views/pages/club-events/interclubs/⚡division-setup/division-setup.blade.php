<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator title="{{ __('Division Setup') }}">
        <x-slot:subtitle>{{ __('Define the opponent teams for each division, once per season.') }}</x-slot:subtitle>
        <x-slot:middle class="justify-end!">
            <x-select
                :options="$seasons"
                option-label="name"
                option-value="id"
                wire:model.live="seasonId"
                placeholder="{{ __('Select a season') }}" />
        </x-slot:middle>
    </x-header>

    @if (! $seasonId)
        <x-card class="mt-4 border-none">
            <p class="py-12 text-center text-sm text-gray-500">{{ __('Select a season to manage divisions.') }}</p>
        </x-card>
    @elseif ($leagues->isEmpty())
        <x-card class="mt-4 border-none">
            <p class="py-12 text-center text-sm text-gray-500">{{ __('No divisions found for this season. Create your club\'s teams first.') }}</p>
        </x-card>
    @else
        <div class="grid gap-6 lg:grid-cols-3">

            {{-- Left panel: division list --}}
            <div class="space-y-2">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Divisions') }}</p>
                @foreach ($leagues->sortBy(fn ($l) => match($l->category) { 'MEN' => 1, 'VETERANS' => 2, 'WOMEN' => 3, default => 99 }) as $league)
                    @php
                        $meta  = $categoryMeta[$league->category] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'label' => $league->category];
                        $count = $league->teams->filter(fn ($t) => $t->club?->licence !== config('app.club_licence'))->count();
                        $active = $selectedLeagueId === $league->id;
                    @endphp
                    <button
                        type="button"
                        wire:click="selectLeague({{ $league->id }})"
                        class="flex w-full items-center gap-3 rounded-xl border px-4 py-3 text-left transition-colors
                            {{ $active ? $meta['bg'] . ' ' . $meta['border'] : 'border-base-200 bg-base-100 hover:bg-base-200' }}"
                    >
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $meta['dot'] }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold {{ $active ? $meta['text'] : '' }}">{{ $league->division }}</p>
                            <p class="text-xs opacity-60 {{ $active ? $meta['text'] : '' }}">{{ $meta['label'] }}</p>
                        </div>
                        <span class="text-xs font-medium {{ $active ? $meta['text'] : 'text-gray-400' }}">{{ $count }}</span>
                    </button>
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
                        $meta = $categoryMeta[$selectedLeague?->category ?? ''] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-200', 'text' => 'text-gray-700', 'dot' => 'bg-gray-400', 'label' => ''];
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
                            label="{{ __('Add opponent') }}"
                            wire:click="openAddModal" />
                    </div>

                    @if ($participants->isEmpty())
                        <div class="flex h-40 items-center justify-center rounded-xl border border-dashed border-base-300">
                            <div class="text-center">
                                <p class="text-sm text-gray-400">{{ __('No opponents yet for this division.') }}</p>
                                <x-button class="btn-primary btn-sm mt-3" icon="o-plus" label="{{ __('Add first opponent') }}" wire:click="openAddModal" />
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
                                            tooltip="{{ __('Remove') }}"
                                            wire:click="confirmDelete({{ $team->id }})" />
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
    <x-modal wire:model="addModal" title="{{ __('Add opponent') }}" separator>
        <div class="space-y-4">
            <x-input
                label="{{ __('Club name') }}"
                wire:model="formClubName"
                placeholder="ex: TT Wavre"
                icon="o-building-office" />
            <x-input
                label="{{ __('Address (optional)') }}"
                wire:model="formClubStreet"
                placeholder="ex: Rue de la Gare 10, 1300 Wavre"
                icon="o-map-pin" />
            <x-input
                label="{{ __('Team letter') }}"
                wire:model="formTeamLetter"
                placeholder="A"
                icon="o-tag"
                class="max-w-[100px]" />
        </div>
        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('addModal', false)" />
            <x-button class="btn-primary" label="{{ __('Add') }}" wire:click="addParticipant" spinner />
        </x-slot:actions>
    </x-modal>

    {{-- Modal delete --}}
    <x-modal subtitle="{{ __('Warning!') }}" title="{{ __('Remove participant') }}" wire:model="deleteModal">
        <p>{{ __('Are you sure you want to remove this opponent from the division?') }}</p>
        <x-slot:actions>
            <x-button label="{{ __('Cancel') }}" wire:click="$set('deleteModal', false)" />
            <x-button class="btn-error" label="{{ __('Remove') }}" spinner wire:click="deleteParticipant" />
        </x-slot:actions>
    </x-modal>
</div>
