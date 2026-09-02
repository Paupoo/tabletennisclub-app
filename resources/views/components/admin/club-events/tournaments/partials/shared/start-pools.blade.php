{{-- Shared pools/matches section for both tabs and steps start views.
     Pass ['isLocked' => true] when including to disable actions after tournament launch. --}}

{{-- Stale config warning --}}
@if ($this->poolsStale)
    <div class="mt-6 flex items-start gap-3 p-4 rounded-xl bg-warning/10 border border-warning/30">
        <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-warning-content shrink-0 mt-0.5" />
        <div>
            <p class="font-semibold text-sm text-warning-content">{{ __('Configuration changed') }}</p>
            <p class="text-xs text-base-content/60 mt-0.5">{{ __('Pool or match settings have been modified. Regenerate pools and matches before launching.') }}</p>
        </div>
    </div>
@endif

{{-- Checklist --}}
<div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-3">
    @php
        $checks = [
            ['label' => __('Tournament saved'), 'ok' => (bool) $tournamentId],
            ['label' => __('Registrations closed'), 'ok' => $this->registrationClosed],
            ['label' => __('Pools generated'), 'ok' => $this->poolsGenerated],
            ['label' => __('Matches generated'), 'ok' => $this->matchesGenerated],
        ];
    @endphp
    @foreach ($checks as $check)
        <div @class(['flex items-center gap-3 p-3 rounded-xl border', 'border-success/40 bg-success/5' => $check['ok'], 'border-base-300 bg-base-200/40' => !$check['ok']])>
            <x-icon :name="$check['ok'] ? 'o-check-circle' : 'o-clock'"
                @class(['w-5 h-5', 'text-success' => $check['ok'], 'text-base-content/30' => !$check['ok']]) />
            <span class="text-xs font-medium">{{ $check['label'] }}</span>
        </div>
    @endforeach
</div>

{{-- Generate Pools --}}
<x-header :title="__('Pools')" :subtitle="__('Distribute players automatically, then adjust if needed')" class="mt-8" size="md">
    <x-slot:actions>
        <x-button label="{{ $this->poolsGenerated ? __('Regenerate Pools') : __('Generate Pools') }}"
            icon="o-user-group"
            class="btn-primary btn-sm"
            wire:click="generatePools"
            spinner="generatePools"
            :disabled="!$tournamentId || !$this->registrationClosed || $isLocked" />
    </x-slot:actions>
</x-header>

{{-- Pool grid --}}
@if ($this->poolsGenerated)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($this->pools as $poolId => $data)
            <x-card wire:key="pool-card-{{ $poolId }}" title="{{ $data['name'] }}" shadow compact class="border-0">
                <div>
                    <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 text-muted text-sm">
                        <span>{{ __('Player') }}</span>
                        <div class="flex gap-4">
                            <span class="w-10 text-right">{{ __('Rank.') }}</span>
                            <span class="w-6 text-right">{{ __('Pts') }}</span>
                        </div>
                    </div>

                    <div x-init="initSortable($el, $wire)" data-team-id="{{ $poolId }}" class="min-h-25 space-y-1">
                        @foreach ($data['players'] as $player)
                            <div wire:key="player-{{ $player['id'] }}" data-id="{{ $player['id'] }}"
                                @class(['flex justify-between items-center border-b border-base-300/30 py-1 group', 'text-primary underline underline-offset-4 decoration-2' => $player['id'] === auth()->id()])>
                                <div class="flex items-center gap-2 truncate">
                                    {{-- La zone de saisie du glissement : hors d'elle, le doigt fait défiler. --}}
                                    <span data-drag-handle
                                        class="-m-2 flex h-8 w-8 shrink-0 cursor-grab items-center justify-center p-2 active:cursor-grabbing"
                                        aria-label="{{ __('Move :player', ['player' => $player['name']]) }}">
                                        <x-icon name="o-bars-3"
                                            class="w-4 h-4 opacity-20 group-hover:opacity-100 transition-opacity" />
                                    </span>
                                    <span class="truncate font-medium">{{ $player['name'] }}</span>
                                </div>
                                <div class="flex gap-5 items-center">
                                    <span class="opacity-70 font-mono w-10 text-right text-xs font-bold">{{ $player['rank'] }}</span>
                                    <span class="font-bold w-6 text-right">{{ $player['pts'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>

    {{-- Generate Matches --}}
    <div class="mt-6 flex justify-center">
        {{-- The state rides on the icon, not on a check glued to the label. --}}
        <x-button :label="$this->matchesGenerated ? __('Matches ready') : __('Generate Matches')"
            :icon="$this->matchesGenerated ? 'o-check' : 'o-table-cells'"
            :class="$this->matchesGenerated ? 'btn-success btn-outline' : 'btn-secondary'"
            wire:click="generateMatches"
            spinner="generateMatches"
            :disabled="$this->matchesGenerated || $isLocked" />
    </div>

    {{-- Matches verification --}}
    @if ($this->matchesGenerated)
        <div x-data="{ open: false }" class="mt-6">
            <button type="button" @click="open = !open"
                class="flex items-center gap-2 text-sm text-base-content/50 hover:text-base-content transition-colors mx-auto">
                <x-icon name="o-table-cells" class="w-4 h-4" />
                <span x-text="open ? '{{ __('Hide matches') }}' : '{{ __('Verify matches') }}'"></span>
                <x-icon name="o-chevron-down" class="w-3 h-3 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
            </button>

            <div x-show="open" x-transition class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($this->matchesByPool as $poolId => $poolData)
                    <x-card wire:key="matches-pool-{{ $poolId }}" title="{{ $poolData['name'] }}" compact shadow class="border-0">
                        <div class="space-y-1">
                            @foreach ($poolData['matches'] as $match)
                                <div class="flex items-center gap-2 py-1 border-b border-base-300/30 text-sm">
                                    <span class="font-mono text-xs text-muted w-5 text-right">{{ $match['order'] }}</span>
                                    <span class="flex-1 truncate">{{ $match['p1'] }}</span>
                                    <span class="text-xs text-muted font-bold">vs</span>
                                    <span class="flex-1 truncate text-right">{{ $match['p2'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach
            </div>
        </div>
    @endif
@else
    <div class="flex flex-col items-center py-16 text-muted">
        <x-icon name="o-user-group" class="w-12 h-12 mb-3" />
        <p class="text-sm">{{ $this->registrationClosed ? __('Click "Generate Pools" to distribute players.') : __('Close registrations first.') }}</p>
    </div>
@endif
