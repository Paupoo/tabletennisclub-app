{{--
    Le repli du panneau collé, sous xl : la colonne de droite n'a plus la place,
    mais le verdict doit rester sous les yeux pendant qu'on tape. La barre ne
    porte que les chiffres ; le détail et les recommandations restent dans le
    panneau, plus bas.

    Les classes sont écrites en toutes lettres : Tailwind scanne les sources à
    la recherche de chaînes littérales, une classe assemblée à l'exécution
    (`border-{{ $color }}/40`) n'est jamais générée.
--}}
@php
    $sim = $this->simulation;
    $bar = match ($sim->riskLevel) {
        'ok' => [
            'label' => __('Feasible'),
            'box' => 'border-success/40 bg-success/5',
            'dot' => 'bg-success',
            'text' => 'text-success',
        ],
        'warning' => [
            'label' => __('Tight'),
            'box' => 'border-warning/40 bg-warning/5',
            'dot' => 'bg-warning',
            'text' => 'text-warning-content',
        ],
        default => [
            'label' => __('Not feasible'),
            'box' => 'border-error/40 bg-error/5',
            'dot' => 'bg-error',
            'text' => 'text-error',
        ],
    };
    $barHours = intdiv($sim->estimatedMinutes, 60);
    $barMins = $sim->estimatedMinutes % 60;
@endphp

<div class="sticky top-2 z-10 xl:hidden">
    <div class="flex flex-wrap items-stretch gap-x-6 gap-y-2 rounded-xl border px-4 py-2.5 backdrop-blur {{ $bar['box'] }}">

        <div class="flex items-center gap-2 border-base-300 pe-4 sm:border-e">
            <span class="h-2 w-2 shrink-0 rounded-full {{ $bar['dot'] }}"></span>
            <span class="text-sm font-bold {{ $bar['text'] }}">{{ $bar['label'] }}</span>
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-base-content/40">{{ __('Estimated duration') }}</p>
            <p class="text-sm font-semibold tabular-nums">
                {{ $barHours > 0 ? $barHours . 'h' . ($barMins > 0 ? $barMins : '') : $barMins . 'min' }}
                <span class="font-normal text-base-content/40">/ {{ intdiv($tournament_minutes, 60) }}h</span>
            </p>
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-base-content/40">{{ __('Table occupancy') }}</p>
            <p class="text-sm font-semibold tabular-nums">{{ min($sim->tableOccupancyPercent, 999) }}&nbsp;%</p>
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-base-content/40">{{ __('Total matches') }}</p>
            <p class="text-sm font-semibold tabular-nums">{{ $sim->grandTotalMatches }}</p>
        </div>

        <div>
            <p class="text-xs font-bold uppercase tracking-wider text-base-content/40">{{ __('Capacity') }}</p>
            <p class="text-sm font-semibold tabular-nums">{{ $sim->totalPlayers }}</p>
        </div>
    </div>
</div>
