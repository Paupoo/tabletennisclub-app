{{-- Stale config warning --}}
@if ($this->poolsStale)
    <div class="mt-6 flex items-start gap-3 p-4 rounded-xl bg-warning/10 border border-warning/30">
        <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-warning shrink-0 mt-0.5" />
        <div>
            <p class="font-semibold text-sm text-warning">{{ __('Configuration changed') }}</p>
            <p class="text-xs text-base-content/60 mt-0.5">{{ __('Pool or match settings have been modified. Regenerate pools and matches before launching.') }}</p>
        </div>
    </div>
@endif

{{-- Checklist --}}
<div class="mt-8 grid grid-cols-2 md:grid-cols-4 gap-3">
    @php
        $checks = [
            ['label' => 'Tournoi sauvegardé', 'ok' => (bool) $tournamentId],
            ['label' => 'Inscriptions fermées', 'ok' => $this->registrationClosed],
            ['label' => 'Poules générées', 'ok' => $this->poolsGenerated],
            ['label' => 'Matchs générés', 'ok' => $this->matchesGenerated],
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
<x-header title="{{ __('Pools') }}" subtitle="Distribute players automatically, then adjust if needed" class="mt-8" size="md">
    <x-slot:actions>
        <x-button label="{{ $this->poolsGenerated ? __('Regenerate Pools') : __('Generate Pools') }}"
            icon="o-user-group"
            class="btn-primary btn-sm"
            wire:click="generatePools"
            spinner="generatePools"
            :disabled="!$tournamentId || !$this->registrationClosed || $this->isLaunched" />
    </x-slot:actions>
</x-header>

{{-- Pool grid --}}
@if ($this->poolsGenerated)
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($this->pools as $poolId => $data)
            <x-card wire:key="pool-card-{{ $poolId }}" title="{{ $data['name'] }}" shadow compact class="border-0">
                <div>
                    <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 opacity-50 text-sm">
                        <span>{{ __('Player') }}</span>
                        <div class="flex gap-4">
                            <span class="w-10 text-right">{{ __('Rank.') }}</span>
                            <span class="w-6 text-right">{{ __('Pts') }}</span>
                        </div>
                    </div>

                    <div x-init="initSortable($el, $wire)" data-team-id="{{ $poolId }}" class="min-h-[100px] space-y-1">
                        @foreach ($data['players'] as $player)
                            <div wire:key="player-{{ $player['id'] }}" data-id="{{ $player['id'] }}"
                                @class(['flex justify-between items-center border-b border-base-300/30 py-1 group cursor-grab active:cursor-grabbing', 'text-primary underline underline-offset-4 decoration-2' => $player['id'] === auth()->id()])>
                                <div class="flex items-center gap-2 truncate">
                                    <x-icon name="o-bars-3"
                                        class="w-4 h-4 opacity-20 group-hover:opacity-100 transition-opacity" />
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
        <x-button :label="$this->matchesGenerated ? __('Matches ready') . ' ✓' : __('Generate Matches')"
            icon="o-table-cells"
            :class="$this->matchesGenerated ? 'btn-success btn-outline' : 'btn-secondary'"
            wire:click="generateMatches"
            spinner="generateMatches"
            :disabled="$this->matchesGenerated || $this->isLaunched" />
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
                                    <span class="font-mono text-xs opacity-40 w-5 text-right">{{ $match['order'] }}</span>
                                    <span class="flex-1 truncate">{{ $match['p1'] }}</span>
                                    <span class="text-xs opacity-40 font-bold">vs</span>
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
    <div class="flex flex-col items-center py-16 opacity-40">
        <x-icon name="o-user-group" class="w-12 h-12 mb-3" />
        <p class="text-sm">{{ $this->registrationClosed ? __('Click "Generate Pools" to distribute players.') : __('Close registrations first.') }}</p>
    </div>
@endif

{{-- Launch Section --}}
<div class="mt-16 flex flex-col items-center text-center animate-in zoom-in-95 duration-500 pb-12">
    @if ($this->isLaunched)
        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6 bg-success/10 text-success">
            <x-icon name="o-rocket-launch" class="w-12 h-12" />
        </div>
        <h2 class="text-3xl font-black italic uppercase tracking-tight text-success">{{ __('Tournament launched!') }}</h2>
        <p class="max-w-md text-base-content/60 mt-4">
            {{ __('The tournament is underway. No further modifications are allowed.') }}
        </p>
        <x-button label="{{ __('Go to Live Center') }}" icon="o-arrow-right"
            class="btn-success btn-lg mt-10 shadow-xl shadow-success/20"
            :link="route('admin.tournaments.live-center', $tournamentId)" />
    @else
        <div @class(['w-20 h-20 rounded-full flex items-center justify-center mb-6', 'bg-primary/10 text-primary' => $this->matchesGenerated, 'bg-base-200 text-base-content/30' => !$this->matchesGenerated])>
            <x-icon name="o-check-badge" class="w-12 h-12" />
        </div>
        <h2 class="text-3xl font-black italic uppercase tracking-tight">Ready to go?</h2>
        <p class="max-w-md text-base-content/60 mt-4">
            Configuration is complete. Click below to generate the tournament brackets and notify all participants.
        </p>

        <x-button label="{{ __('Launch Tournament') }}" icon="o-play"
            class="btn-primary btn-lg mt-10 shadow-xl shadow-primary/20" wire:click="launch" spinner="launch"
            :disabled="!$this->matchesGenerated || $this->poolsStale" />
    @endif
</div>

{{-- Require close registrations modal --}}
<x-modal wire:model="showRequireCloseRegistrationsModal" title="{{ __('Registrations are still open') }}" class="backdrop-blur">
    <div class="space-y-4">
        <div class="flex items-start gap-3 p-4 bg-warning/10 border border-warning/20 rounded-xl text-sm">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5 text-warning" />
            <div class="space-y-1">
                <p class="font-semibold">{{ __('You must close registrations before launching.') }}</p>
                <p class="text-base-content/70">{{ __('Closing registrations will remove any players still on the waiting list. This cannot be undone.') }}</p>
            </div>
        </div>
        @php $waitlistCount = $this->waitlist->count(); @endphp
        @if ($waitlistCount > 0)
            <p class="text-sm text-base-content/70">
                {{ trans_choice('1 person on the waiting list will be removed.|:count people on the waiting list will be removed.', $waitlistCount, ['count' => $waitlistCount]) }}
            </p>
        @endif
    </div>
    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" wire:click="$set('showRequireCloseRegistrationsModal', false)" />
        <x-button label="{{ __('Close registrations & launch') }}" icon="o-rocket-launch" class="btn-warning"
            wire:click="confirmCloseAndLaunch" spinner="confirmCloseAndLaunch" />
    </x-slot:actions>
</x-modal>
