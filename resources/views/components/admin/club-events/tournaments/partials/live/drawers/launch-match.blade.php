<x-drawer wire:model="launchDrawer" :title="__('Launch a match')" right separator with-close-button class="lg:w-1/3">

    <div class="space-y-4">
        <p class="text-sm text-base-content/60">
            {{ __('Select a match to assign to this table. The recommended match is highlighted.') }}
        </p>

        @if ($this->upcomingMatches->isEmpty())
            <div class="flex flex-col items-center py-16 text-muted">
                <x-icon name="o-no-symbol" class="w-12 h-12 mx-auto mb-3" />
                <p class="text-sm">{{ __('No matches scheduled.') }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->upcomingMatches as $index => $match)
                    @php
                        $busyIds     = $this->busyPlayerIds;
                        $isFirst     = $index === 0;
                        $isPool      = $match->pool_id !== null;
                        $isReady     = $match->player1_id !== null && $match->player2_id !== null;
                        $isDoubles   = $match->pair1_id !== null;
                        $side1Ids    = $isDoubles
                            ? collect([$match->pair1?->player1_id, $match->pair1?->player2_id])->filter()
                            : collect([$match->player1_id])->filter();
                        $side2Ids    = $isDoubles
                            ? collect([$match->pair2?->player1_id, $match->pair2?->player2_id])->filter()
                            : collect([$match->player2_id])->filter();
                        $p1Busy      = $side1Ids->intersect($busyIds)->isNotEmpty();
                        $p2Busy      = $side2Ids->intersect($busyIds)->isNotEmpty();
                        $hasConflict = $isReady && ($p1Busy || $p2Busy);
                        $label       = $isPool ? ($match->pool?->name ?? __('Pool')) : __('Bracket');
                        $side1Name   = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                        $side2Name   = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                        $refereeName = $match->referee?->full_name;
                    @endphp

                    <div class="relative group" wire:key="launch-match-{{ $match->id }}">
                        @if ($hasConflict)
                            <div class="absolute -top-2 left-4 z-10">
                                <x-badge value="{{ __('Player busy') }}" icon="o-exclamation-triangle" class="badge-warning badge-xs font-bold shadow-sm" />
                            </div>
                        @elseif ($isFirst && ! $hasConflict)
                            <div class="absolute -top-2 left-4 z-10">
                                <x-badge value="{{ __('Recommended') }}" class="badge-primary badge-xs font-bold shadow-sm" />
                            </div>
                        @endif

                        <button type="button" wire:click="startMatch({{ $match->id }})"
                            @class([
                                'w-full text-left p-4 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between',
                                'border-warning/60 bg-warning/5 opacity-60' => $hasConflict,
                                'border-primary bg-primary/5 ring-1 ring-primary/20' => $isFirst && ! $hasConflict,
                                'border-base-200 hover:border-primary/40 bg-base-100' => ! $isFirst && ! $hasConflict,
                            ])>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-badge :value="$label"
                                        class="{{ $isPool ? 'badge-ghost' : 'badge-warning' }} badge-xs uppercase font-bold" />
                                </div>
                                <div class="flex flex-col">
                                    <span @class(['font-bold text-sm truncate', 'text-warning-content line-through' => $p1Busy])>{{ $side1Name }}</span>
                                    <span class="text-xs opacity-30 italic font-black my-0.5">VS</span>
                                    <span @class(['font-bold text-sm truncate', 'text-warning-content line-through' => $p2Busy])>{{ $side2Name }}</span>
                                </div>
                                @if ($refereeName)
                                    <div class="mt-1.5 flex items-center gap-1 text-xs text-muted">
                                        <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                                        <span>{{ $refereeName }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="ml-4 shrink-0">
                                @if ($hasConflict)
                                    <x-icon name="o-exclamation-triangle" class="w-5 h-5 text-warning-content" />
                                @else
                                    {{-- Décor : c'est la carte entière qui lance le match, et un
                                         bouton ne peut pas en contenir un autre. --}}
                                    <span aria-hidden="true"
                                        class="btn btn-circle {{ $isFirst ? 'btn-primary' : 'btn-ghost' }} btn-sm pointer-events-none">
                                        <x-icon name="o-play" class="h-4 w-4" />
                                    </span>
                                @endif
                            </div>
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <x-slot:actions>
        <x-button :label="__('Cancel')" @click="$wire.launchDrawer = false" />
    </x-slot:actions>

</x-drawer>
