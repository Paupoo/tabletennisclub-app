{{--
    La file d'attente, à côté des tables plutôt que derrière un onglet.

    Le jour J, l'organisateur répète un seul geste : une table se libère, quel
    match lancer dessus ? L'état de la salle vivait dans « Tables » et la file
    dans « À venir » -- impossible de voir les deux. Ce panneau était l'onglet
    À venir ; il tient maintenant la colonne de droite de la régie.

    Le drapeau « joueur en piste » vient de $this->queue, pas d'un calcul
    recopié ici : il l'était, à l'identique, dans cette vue et dans le tiroir
    de lancement.
--}}
@php
    $roundLabels = [
        'round_16' => __('Round of 16'),
        'quarterfinal' => __('Quarterfinal'),
        'semifinal' => __('Semifinal'),
        'final' => __('Final'),
        'bronze' => __('3rd place'),
    ];
    $nextTaken = false;
@endphp

<x-card class="border border-base-300" shadow>
    <x-slot:title>
        <div class="flex items-center gap-2">
            <x-icon name="o-megaphone" class="h-4 w-4 shrink-0 text-base-content/50" />
            <span class="text-base font-semibold">{{ __('Match queue') }}</span>
            @if ($this->queue->isNotEmpty())
                <x-badge :value="$this->queue->count()" class="badge-ghost badge-sm ml-auto" />
            @endif
        </div>
    </x-slot:title>

    @if ($this->queue->isEmpty())
        <div class="flex flex-col items-center py-10 text-muted">
            <x-icon name="o-check-circle" class="mb-3 h-10 w-10" />
            <p class="text-sm">{{ __('All matches are done or in progress.') }}</p>
        </div>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($this->queue as $entry)
                @php
                    $match = $entry['match'];
                    $isPool = $match->pool_id !== null;
                    $isDoubles = $match->pair1_id !== null;
                    $label = $isPool
                        ? ($match->pool?->name ?? __('Pool'))
                        : ($roundLabels[$match->round] ?? __('Bracket'));
                    $side1Name = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                    $side2Name = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                    // Le premier match jouable et libre de conflit : celui à lancer.
                    $isNext = $entry['ready'] && ! $entry['blocked'] && ! $nextTaken;
                    $nextTaken = $nextTaken || $isNext;
                @endphp

                <div wire:key="queue-{{ $match->id }}" @class([
                    'rounded-xl border p-3 transition-colors',
                    'border-primary bg-primary/5' => $isNext,
                    'border-warning/50 bg-warning/5' => $entry['blocked'],
                    'border-base-300 bg-base-100' => ! $isNext && ! $entry['blocked'],
                    'opacity-60' => ! $entry['ready'],
                ])>
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <x-badge :value="$label"
                            class="{{ $isPool ? 'badge-ghost' : 'badge-warning' }} badge-xs font-bold uppercase" />

                        @if ($entry['blocked'])
                            {{-- Nommer qui bloque : un arbitre pris se remplace, un joueur en piste s'attend. --}}
                            <span class="flex items-center gap-1 text-xs font-bold text-warning-content">
                                <x-icon name="o-exclamation-triangle" class="h-3 w-3 shrink-0" />
                                {{ $entry['side1Blocked'] || $entry['side2Blocked'] ? __('Player busy') : __('Referee busy') }}
                            </span>
                        @elseif ($isNext)
                            <x-badge :value="__('Next')" class="badge-primary badge-xs" />
                        @elseif (! $entry['ready'])
                            <x-badge :value="__('Awaiting')" class="badge-ghost badge-xs text-muted" />
                        @endif
                    </div>

                    @if ($entry['ready'])
                        <p @class(['truncate text-sm font-semibold', 'text-warning-content line-through' => $entry['side1Blocked']])>
                            {{ $side1Name }}
                        </p>
                        <p class="my-0.5 text-xs font-black italic opacity-25">VS</p>
                        <p @class(['truncate text-sm font-semibold', 'text-warning-content line-through' => $entry['side2Blocked']])>
                            {{ $side2Name }}
                        </p>
                    @else
                        <p class="text-sm italic text-muted">{{ __('TBD') }}</p>
                    @endif

                    @if ($match->referee)
                        <p @class([
                            'mt-1.5 flex items-center gap-1 text-xs',
                            'font-semibold text-warning-content' => $entry['refereeBlocked'],
                            'text-muted' => ! $entry['refereeBlocked'],
                        ])>
                            <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
                            <span @class(['truncate', 'line-through' => $entry['refereeBlocked']])>{{ $match->referee->full_name }}</span>
                        </p>
                    @elseif (! $isPool && $entry['ready'])
                        <p class="mt-1.5 flex items-center gap-1 text-xs italic text-muted">
                            <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
                            <span>{{ in_array($match->round, ['final', 'bronze'], true) ? __('Organisation') : __('Referee needed') }}</span>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-card>
