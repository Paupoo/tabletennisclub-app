{{--
    Ce dont un total combiné est fait : interclubs d'un côté, tournois
    officiels de l'autre. Toujours sous le total « tout confondu » — un taux
    mélangé ne veut rien dire seul, l'adversité n'est pas la même.
--}}
@props(['totals'])

<div {{ $attributes->class('mt-3 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-base-content/60') }}
    data-results-split>
    <span>
        <span class="font-semibold">{{ __('Interclub') }}</span>
        · {{ trans_choice(':count individual match|:count individual matches', $totals['interclub']['played']) }}
        @if ($totals['interclub']['played'] > 0)
            · {{ $totals['interclub']['rate'] }}%
        @endif
    </span>
    <span>
        <span class="font-semibold">{{ __('Official tournaments') }}</span>
        · {{ trans_choice(':count individual match|:count individual matches', $totals['tournaments']['played']) }}
        @if ($totals['tournaments']['played'] > 0)
            · {{ $totals['tournaments']['rate'] }}%
        @endif
    </span>
</div>
