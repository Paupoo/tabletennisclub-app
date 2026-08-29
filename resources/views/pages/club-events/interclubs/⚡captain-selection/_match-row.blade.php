{{--
    Une ligne de rencontre, dans les deux sens de lecture de la matrice.

    En mode « équipe » la colonne de gauche porte la journée et la date ; en mode
    « journée » elle porte l'équipe et sa division. Tout le reste — statut en
    phrase, disponibilités, actions — est identique, parce que c'est la même
    rencontre vue sous un autre angle.

    Attend : $ic (rencontre), $mode ('team'|'day'), $matchDayMap.
--}}
@php
    $isPast = $ic['is_past'];
    $maxP = $ic['max_players'];
    $avCount = $ic['available_count'];

    [$statusBarColor, $statusLabel] = match ($ic['status']) {
        'confirmed' => ['bg-success', __('Lineup sent')],
        'actionable' => ['bg-warning', __('Ready to compose')],
        'urgent' => ['bg-error', __('Needs attention')],
        'past' => ['bg-base-300', __('Played')],
        default => ['bg-base-300', __('Upcoming')],
    };
@endphp

<div data-match-row wire:key="match-{{ $mode }}-{{ $ic['id'] }}" @class([
    'px-4 py-3 bg-base-100 transition-colors',
    'bg-base-50' => $isPast,
    'bg-error/5' => $ic['status'] === 'urgent',
])>
    {{-- Deux gabarits explicites, pas un flex-wrap : sous `sm` la ligne s'empile,
         au-dessus elle s'aligne. Le wrap laissait la colonne du nom passer sous la
         taille de son contenu, qui débordait alors par-dessus les compteurs. --}}
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">

        <div class="flex min-w-0 flex-1 items-center gap-3">
            {{-- La barre porte une couleur ; le statut est aussi écrit en toutes
                 lettres plus bas, jamais par la couleur seule. --}}
            <div class="{{ $statusBarColor }} h-10 w-1 shrink-0 rounded-full" aria-hidden="true"></div>

            @if ($mode === 'day')
                <div class="w-14 shrink-0 text-center">
                    <div class="text-xs font-bold text-base-content/60">{{ __('Team') }}</div>
                    <div class="text-base font-bold leading-none">{{ $ic['team_name'] }}</div>
                    <div class="text-xs text-base-content/60">{{ $ic['team_division'] }}</div>
                </div>
            @else
                <div class="w-14 shrink-0 text-center">
                    <div class="text-xs font-bold text-base-content/60">{{ __('Match day') }}</div>
                    <div class="text-base font-bold leading-none">{{ $matchDayMap[$ic['wk']] ?? $ic['wk'] }}</div>
                    <div class="tabular-nums text-xs text-base-content/60">{{ substr($ic['date'], 0, 5) }}</div>
                </div>
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex min-w-0 items-center gap-1.5">
                    @if ($ic['is_home'])
                        <x-badge class="badge-neutral badge-sm shrink-0 font-bold" value="{{ __('Home') }}" />
                    @else
                        <x-badge class="badge-ghost badge-sm shrink-0 border border-base-300 font-bold" value="{{ __('Away') }}" />
                    @endif
                    <span class="truncate text-sm font-bold">{{ $ic['opponent'] }}</span>
                </div>
                {{-- Le statut en phrase : « 3 dispo sur 4 » se lit, « 3/4 dispo »
                     en marge droite se déchiffre. --}}
                <div class="mt-1 text-sm text-base-content/70">
                    <span class="tabular-nums">{{ $ic['time'] }}</span>
                    @if ($mode === 'day')
                        <span aria-hidden="true">·</span>
                        <span class="tabular-nums">{{ substr($ic['date'], 0, 5) }}</span>
                    @endif
                    @if (! $isPast)
                        <span aria-hidden="true">·</span>
                        <span @class(['font-semibold text-error' => $ic['status'] === 'urgent'])>{{ $statusLabel }}</span>
                        <span aria-hidden="true">·</span>
                        {{ __(':available available out of :max', ['available' => $avCount, 'max' => $maxP]) }}
                        @if ($ic['selected_count'] > 0)
                            <span aria-hidden="true">·</span>
                            {{ trans_choice(':count selected|:count selected', $ic['selected_count'], ['count' => $ic['selected_count']]) }}
                        @endif
                    @endif
                </div>
                @if ($isPast && ! empty($ic['selected_player_names']))
                    <div class="mt-1 text-sm text-base-content/60">
                        {{ implode(', ', $ic['selected_player_names']) }}
                    </div>
                @endif
            </div>
        </div>

        {{-- Une action nommée, le reste derrière un menu nommé : la règle de
             row-menu, appliquée par 9 pages index. --}}
        @if (! $isPast)
            <div class="shrink-0">
                <x-admin.shared.row-menu
                    :label="__('Compose')"
                    icon="o-pencil-square"
                    wire-click="openSelection({{ $ic['id'] }})">
                    <x-menu-item
                        icon="o-envelope"
                        :title="__('Request availability')"
                        wire:click="confirmAvailabilityRequest({{ $ic['id'] }})" />
                </x-admin.shared.row-menu>
            </div>
        @endif
    </div>
</div>
