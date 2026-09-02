<x-drawer wire:model="launchDrawer" :title="__('Launch a match')" right separator with-close-button
    class="w-11/12 md:w-[480px]">

    {{--
        Le drapeau « joueur en piste » vient de $this->queue. Il était recalculé
        ici en Blade, à l'identique de l'onglet À venir : deux copies du même
        intersect, à tenir synchronisées à la main.
    --}}
    <div class="space-y-4">
        <p class="text-sm text-base-content/60">
            {{ __('Select a match to assign to this table. The recommended match is highlighted.') }}
        </p>

        @if ($this->queue->isEmpty())
            <div class="flex flex-col items-center py-16 text-muted">
                <x-icon name="o-no-symbol" class="mx-auto mb-3 h-12 w-12" />
                <p class="text-sm">{{ __('No matches scheduled.') }}</p>
            </div>
        @else
            @php $recommendedTaken = false; @endphp

            <div class="space-y-3">
                @foreach ($this->queue as $entry)
                    @php
                        $match = $entry['match'];
                        $isPool = $match->pool_id !== null;
                        $isDoubles = $match->pair1_id !== null;
                        $label = $isPool ? ($match->pool?->name ?? __('Pool')) : __('Bracket');
                        $side1Name = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                        $side2Name = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                        $isRecommended = $entry['ready'] && ! $entry['blocked'] && ! $recommendedTaken;
                        $recommendedTaken = $recommendedTaken || $isRecommended;
                    @endphp

                    <div class="group relative" wire:key="launch-match-{{ $match->id }}">
                        @if ($entry['blocked'])
                            <div class="absolute -top-2 left-4 z-10">
                                <x-badge
                                    :value="$entry['side1Blocked'] || $entry['side2Blocked'] ? __('Player busy') : __('Referee busy')"
                                    icon="o-exclamation-triangle" class="badge-warning badge-xs font-bold shadow-sm" />
                            </div>
                        @elseif ($isRecommended)
                            <div class="absolute -top-2 left-4 z-10">
                                <x-badge :value="__('Recommended')" class="badge-primary badge-xs font-bold shadow-sm" />
                            </div>
                        @endif

                        <button type="button" wire:click="startMatch({{ $match->id }})"
                            @class([
                                'flex w-full cursor-pointer items-center justify-between rounded-xl border-2 p-4 text-left transition-all',
                                'border-warning/60 bg-warning/5 opacity-60' => $entry['blocked'],
                                'border-primary bg-primary/5 ring-1 ring-primary/20' => $isRecommended,
                                'border-base-300 bg-base-100 hover:border-primary/40' => ! $isRecommended && ! $entry['blocked'],
                            ])>

                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex items-center gap-2">
                                    <x-badge :value="$label"
                                        class="{{ $isPool ? 'badge-ghost' : 'badge-warning' }} badge-xs font-bold uppercase" />
                                </div>
                                <div class="flex flex-col">
                                    <span @class(['truncate text-sm font-bold', 'text-warning-content line-through' => $entry['side1Blocked']])>{{ $side1Name }}</span>
                                    <span class="my-0.5 text-xs font-black italic opacity-30">VS</span>
                                    <span @class(['truncate text-sm font-bold', 'text-warning-content line-through' => $entry['side2Blocked']])>{{ $side2Name }}</span>
                                </div>
                                @if ($match->referee)
                                    <div @class([
                                        'mt-1.5 flex items-center gap-1 text-xs',
                                        'font-semibold text-warning-content' => $entry['refereeBlocked'],
                                        'text-muted' => ! $entry['refereeBlocked'],
                                    ])>
                                        <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
                                        <span @class(['truncate', 'line-through' => $entry['refereeBlocked']])>{{ $match->referee->full_name }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="ml-4 shrink-0">
                                @if ($entry['blocked'])
                                    <x-icon name="o-exclamation-triangle" class="h-5 w-5 text-warning-content" />
                                @else
                                    {{-- Décor : c'est la carte entière qui lance le match, et un
                                         bouton ne peut pas en contenir un autre. --}}
                                    <span aria-hidden="true"
                                        class="btn btn-circle {{ $isRecommended ? 'btn-primary' : 'btn-ghost' }} btn-sm pointer-events-none">
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
