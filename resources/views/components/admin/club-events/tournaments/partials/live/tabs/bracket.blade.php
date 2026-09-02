@php
    $rounds = $this->knockoutMatches;
    $roundLabels = [
        'round_16' => __('Round of 16'),
        'quarterfinal' => __('Quarter-finals'),
        'semifinal' => __('Semi-finals'),
        'final' => __('Final'),
        'bronze' => __('3rd Place'),
    ];

    // L'arbre ne dessine que la voie principale ; la petite finale vit à côté.
    $mainRounds = collect(['round_16', 'quarterfinal', 'semifinal', 'final'])
        ->filter(fn (string $round): bool => isset($rounds[$round]) && $rounds[$round]->count() > 0)
        ->values();

    $bronze = ($rounds['bronze'] ?? null)?->first();
@endphp

<div @if ($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif class="mt-6">
    @if ($mainRounds->isEmpty() && $bronze === null)
        <div class="flex flex-col items-center py-20 text-muted">
            <x-icon name="o-trophy" class="mb-3 h-12 w-12" />
            <p class="text-sm">{{ __('Bracket not yet generated.') }}</p>
        </div>
    @else
        {{--
            L'arbre, à partir de lg.

            Les tours étaient rendus en colonnes flex indépendantes, chacune en
            justify-around : un quart de finale ne tombait jamais à la hauteur de
            la demie qu'il alimente, donc on ne pouvait pas suivre un joueur à
            l'œil. Les « connecteurs » étaient des traits de 16 px collés à
            droite d'une carte, qui ne reliaient rien.

            Ici, chaque tour occupe une colonne de grille, et la colonne de
            liaison qui le suit est découpée en autant de blocs que le tour
            SUIVANT compte de rencontres. Chaque bloc trace une accolade en
            quatre traits positionnés en pourcentage — pas de SVG, pas de mesure
            JavaScript — si bien que la rencontre d'arrivée tombe exactement à
            mi-hauteur de ses deux alimentantes.
        --}}
        <div class="hidden lg:block">
            <div class="grid items-stretch gap-0"
                style="grid-template-columns: {{ $mainRounds->map(fn (): string => 'minmax(0,1fr) 3rem')->implode(' ') }} minmax(0,1fr);">

                @foreach ($mainRounds as $i => $round)
                    <div class="flex flex-col">
                        <p class="mb-3 h-8 text-center text-xs font-bold uppercase tracking-widest text-base-content/40">
                            {{ $roundLabels[$round] ?? $round }}
                        </p>
                        <div class="flex flex-1 flex-col justify-around gap-4">
                            @foreach ($rounds[$round] as $match)
                                @include('components.admin.club-events.tournaments.partials.live.bracket-match', [
                                    'match' => $match,
                                    'round' => $round,
                                ])
                            @endforeach
                        </div>
                    </div>

                    @if (! $loop->last)
                        @php $pairs = intdiv($rounds[$round]->count(), 2); @endphp
                        <div class="relative mt-11" aria-hidden="true">
                            @for ($pair = 0; $pair < $pairs; $pair++)
                                @php
                                    $unit = 100 / $rounds[$round]->count();
                                    $top = ($pair * 2 + 0.5) * $unit;
                                    $bottom = ($pair * 2 + 1.5) * $unit;
                                @endphp
                                <div class="absolute left-0 right-1/2 h-px bg-base-300" style="top: {{ $top }}%"></div>
                                <div class="absolute left-0 right-1/2 h-px bg-base-300" style="top: {{ $bottom }}%"></div>
                                <div class="absolute left-1/2 w-px bg-base-300"
                                    style="top: {{ $top }}%; height: {{ $bottom - $top }}%"></div>
                                <div class="absolute left-1/2 right-0 h-px bg-base-300"
                                    style="top: {{ ($top + $bottom) / 2 }}%"></div>
                            @endfor
                        </div>
                    @endif
                @endforeach

                {{-- La petite finale prend la place à droite de la finale plutôt
                     que de traîner sous l'arbre. --}}
                @if ($bronze !== null)
                    <div class="col-start-[-2] row-start-1 flex flex-col">
                        <p class="mb-3 h-8 text-center text-xs font-bold uppercase tracking-widest text-info">
                            {{ $roundLabels['bronze'] }}
                        </p>
                        <div class="flex flex-1 flex-col justify-around">
                            @include('components.admin.club-events.tournaments.partials.live.bracket-match', [
                                'match' => $bronze,
                                'round' => 'bronze',
                            ])
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{--
            Repli étroit : les mêmes rencontres, empilées par tour. Un arbre
            demande de la largeur ; sur un téléphone il n'y en a pas, et un
            défilement horizontal de six colonnes ne se lit pas davantage.
        --}}
        <div class="space-y-6 lg:hidden">
            @foreach ($mainRounds as $round)
                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-base-content/45">
                            {{ $roundLabels[$round] ?? $round }}
                        </span>
                        <div class="h-px grow bg-base-300"></div>
                        <span class="text-xs tabular-nums text-base-content/35">
                            {{ $rounds[$round]->where('status', 'completed')->count() }}/{{ $rounds[$round]->count() }}
                        </span>
                    </div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($rounds[$round] as $match)
                            @include('components.admin.club-events.tournaments.partials.live.bracket-match', [
                                'match' => $match,
                                'round' => $round,
                            ])
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if ($bronze !== null)
                <div>
                    <div class="mb-3 flex items-center gap-3">
                        <span class="text-xs font-bold uppercase tracking-widest text-info">{{ $roundLabels['bronze'] }}</span>
                        <div class="h-px grow bg-base-300"></div>
                    </div>
                    @include('components.admin.club-events.tournaments.partials.live.bracket-match', [
                        'match' => $bronze,
                        'round' => 'bronze',
                    ])
                </div>
            @endif
        </div>
    @endif
</div>
