{{--
    La saison en un coup d'œil : une ligne par équipe, une colonne par journée.

    Trois choses que la version précédente ratait :

    1. Les équipes n'étaient nommées que par leur lettre. Trois catégories
       réutilisent les mêmes lettres — « A » existe en hommes, en dames et en
       vétérans. On groupe par catégorie et on ajoute la division.

    2. Une case vide voulait dire deux choses : « rencontre à venir » ou « cette
       catégorie ne joue pas cette semaine ». Or les vétérans jouent justement
       pendant les semaines de repos des seniors : c'est la règle, pas
       l'exception. Les semaines de repos sont désormais teintées, et
       l'imbrication des calendriers devient le sujet au lieu d'être du bruit.

    3. Trente colonnes ne tiennent pas sur un téléphone. Sous `lg`, la matrice
       est transposée : une ligne par journée et par catégorie, ce qui met le
       défilement sur l'axe gratuit — le vertical.

    Attend : $weekSummary, $matchDayMap, $isAdminOrCommittee.
--}}
{{-- Guarded on the weeks, not on the score: once every match has been played
     the score has nothing left to measure, but the season grid is still worth
     reading. --}}
@if ($isAdminOrCommittee && $weekSummary && $weekSummary['weeks'] !== [])
    @php
        $categoryLabels = [
            'MEN' => __('Men'),
            'WOMEN' => __('Women'),
            'VETERANS' => __('Veterans'),
        ];

        $teamsByCategory = collect($weekSummary['teams'])->groupBy(fn (array $t): string => $t['category'] ?? '—');
        $categoryWeeks = $weekSummary['category_weeks'];

        $dotClass = fn (?string $status): ?string => match ($status) {
            'confirmed' => 'bg-success',
            'actionable' => 'bg-warning',
            'urgent' => 'bg-error',
            'past' => 'border border-base-300',
            'future' => 'bg-base-300',
            default => null,
        };

        $statusLabel = fn (?string $status): string => match ($status) {
            'confirmed' => __('Under control'),
            'actionable' => __('Ready to compose'),
            'urgent' => __('Needs attention'),
            'past' => __('Played'),
            default => __('Upcoming'),
        };
    @endphp

    {{-- The team zoom only dims rows, so it stays client-side: routing it
         through Livewire made every chip click re-render the whole page. The
         query string is kept by hand so the view still survives a reload. --}}
    <div
        x-data="{
            zoomed: new URLSearchParams(window.location.search).get('zoomedTeamId'),
            select(id) {
                const next = id === null ? null : String(id);
                this.zoomed = this.zoomed === next ? null : next;
                const url = new URL(window.location);
                this.zoomed === null
                    ? url.searchParams.delete('zoomedTeamId')
                    : url.searchParams.set('zoomedTeamId', this.zoomed);
                window.history.replaceState({}, '', url);
            },
            isDimmed(id) { return this.zoomed !== null && this.zoomed !== String(id); },
        }"
        class="space-y-3 rounded-xl border border-base-200 bg-base-50 px-4 py-4 sm:px-5">

        {{-- Score global : sur mobile, les trois chiffres le disent mieux. --}}
        <div class="hidden flex-wrap items-center gap-2 lg:flex">
            <span class="text-xs font-bold uppercase tracking-widest text-base-content/60">{{ __('Preparation') }}</span>
            @if ($weekSummary['total'] > 0)
                <span class="text-sm font-bold">{{ $weekSummary['ok'] }}/{{ $weekSummary['total'] }} {{ __('weeks ready') }}</span>
            @else
                <span class="text-sm font-bold text-base-content/60">{{ __('Season over') }}</span>
            @endif
        </div>

        {{-- Zoom par équipe : n'estompe que des lignes de la grille, donc réservé
             au desktop. Sur un téléphone, neuf puces coûtaient deux lignes pour
             un geste qui n'existe pas au pouce. --}}
        <div class="hidden flex-wrap gap-1.5 lg:flex">
            <button
                type="button"
                x-on:click="select(null)"
                data-zoom-chip="all"
                class="rounded-full border px-2.5 py-0.5 text-xs font-bold transition-colors"
                x-bind:class="zoomed === null
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-base-200 text-base-content/60 hover:border-base-300'"
            >{{ __('All') }}</button>
            @foreach ($weekSummary['teams'] as $t)
                <button
                    type="button"
                    x-on:click="select({{ $t['id'] }})"
                    data-zoom-chip="{{ $t['id'] }}"
                    class="rounded-full border px-2.5 py-0.5 text-xs font-bold transition-colors"
                    x-bind:class="zoomed === '{{ $t['id'] }}'
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-base-200 text-base-content/60 hover:border-base-300'"
                >{{ $t['name'] }}<span class="ml-1 font-normal">{{ $t['division'] }}</span></button>
            @endforeach
        </div>

        {{-- ── DESKTOP : la grille, catégorie par catégorie ─────────────── --}}
        <div class="-mx-4 hidden overflow-x-auto px-4 sm:mx-0 sm:px-0 lg:block">
            <table class="border-separate border-spacing-0 text-xs">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-base-50 pb-2 pr-4 text-left">
                            <span class="sr-only">{{ __('Team') }}</span>
                        </th>
                        @foreach ($weekSummary['weeks'] as $wk)
                            <th class="w-7 min-w-7 pb-2 text-center font-bold text-base-content/60">
                                {{ $matchDayMap[$wk['wk']] ?? $wk['wk'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                @foreach ($teamsByCategory as $category => $teams)
                    <tbody>
                        <tr>
                            <th colspan="{{ count($weekSummary['weeks']) + 1 }}"
                                class="sticky left-0 z-10 bg-base-50 pb-1 pt-3 text-left text-xs font-bold uppercase tracking-widest text-base-content/60">
                                {{ $categoryLabels[$category] ?? $category }}
                                <span class="font-normal normal-case tracking-normal">
                                    · {{ trans_choice(':count team|:count teams', count($teams), ['count' => count($teams)]) }}
                                </span>
                            </th>
                        </tr>
                        @foreach ($teams as $t)
                            <tr class="transition-opacity duration-150" x-bind:class="isDimmed({{ $t['id'] }}) && 'opacity-30'">
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-base-50 py-1 pr-4 font-bold text-base-content/70">
                                    {{ $t['name'] }}
                                    <span class="font-normal text-base-content/60">{{ $t['division'] }}</span>
                                </td>
                                @foreach ($weekSummary['weeks'] as $wk)
                                    @php
                                        $status = $weekSummary['matrix'][$t['id']][$wk['wk']] ?? null;
                                        $playsThisWeek = in_array($wk['wk'], $categoryWeeks[$category] ?? [], true);
                                        $class = $dotClass($status);
                                    @endphp
                                    {{-- Semaine de repos de la catégorie : la case est teintée,
                                         pas vide. C'est la semaine où une autre catégorie joue. --}}
                                    <td @class(['w-7 min-w-7 py-1 text-center align-middle', 'bg-base-300/60' => ! $playsThisWeek])>
                                        @if ($class)
                                            <span class="inline-block h-3 w-3 rounded-sm {{ $class }}"
                                                title="{{ $t['name'] }} {{ $t['division'] }} — {{ __('Match day') }} {{ $matchDayMap[$wk['wk']] ?? $wk['wk'] }} : {{ $statusLabel($status) }}"></span>
                                            <span class="sr-only">{{ $statusLabel($status) }}</span>
                                        @elseif ($playsThisWeek)
                                            <span class="text-base-content/60" aria-hidden="true">·</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>

        {{-- ── MOBILE : le bilan ────────────────────────────────────────── --}}
        {{-- Pas une transposition de la grille : sur un téléphone on ne vient pas
             scanner un motif, on vient savoir où en est le club. Trois chiffres,
             un verdict écrit par catégorie, puis ce qui demande une action. La
             chronologie complète attend derrière un repli. --}}
        <div class="space-y-3 lg:hidden">

            {{-- Les trois chiffres --}}
            <div class="grid grid-cols-3 gap-2">
                @foreach ([
                    ['n' => $weekSummary['kpi']['todo'], 'label' => __('To do'), 'tone' => 'todo'],
                    ['n' => $weekSummary['kpi']['controlled'], 'label' => __('Under control'), 'tone' => 'ok'],
                    ['n' => $weekSummary['kpi']['upcoming'], 'label' => __('Upcoming'), 'tone' => 'calm'],
                ] as $kpi)
                    <div @class([
                        'rounded-xl border p-3 text-center',
                        'border-error/30 bg-error/5' => $kpi['tone'] === 'todo' && $kpi['n'] > 0,
                        'border-base-200 bg-base-100' => $kpi['tone'] !== 'todo' || $kpi['n'] === 0,
                    ])>
                        <div @class([
                            'text-2xl font-bold tabular-nums leading-none',
                            'text-error' => $kpi['tone'] === 'todo' && $kpi['n'] > 0,
                            'text-success' => $kpi['tone'] === 'ok' && $kpi['n'] > 0,
                        ])>{{ $kpi['n'] }}</div>
                        <div class="mt-1 text-xs leading-tight text-base-content/70">{{ $kpi['label'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Une carte par catégorie : progression + verdict en toutes lettres --}}
            <div class="divide-y divide-base-200 overflow-hidden rounded-xl border border-base-200 bg-base-100">
                @foreach ($weekSummary['categories'] as $standing)
                    @php
                        $remaining = $standing['total'] - $standing['played'];
                        $verdict = match (true) {
                            $standing['todo'] > 0 => ['text-error', 'o-exclamation-triangle',
                                trans_choice(':count match day to handle|:count match days to handle', $standing['todo'], ['count' => $standing['todo']])
                                . ($standing['next_date'] ? ' · ' . $standing['next_date'] : '')],
                            $remaining === 0 => ['text-base-content/60', 'o-check', __('Season over')],
                            // « Tout est sous contrôle » est déjà dit une fois, sous
                            // les cartes. Ici on ne garde que l'échéance.
                            $standing['next_date'] !== null => ['text-base-content/70', 'o-calendar',
                                __('next on :date', ['date' => $standing['next_date']])],
                            default => ['text-base-content/60', 'o-check', __('Season over')],
                        };
                    @endphp
                    <div class="px-3 py-3">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="text-sm font-bold">{{ $categoryLabels[$standing['category']] ?? $standing['category'] }}</span>
                            <span class="ml-auto text-xs tabular-nums text-base-content/60">
                                {{ trans_choice(':count team|:count teams', $standing['teams'], ['count' => $standing['teams']]) }}
                                ·
                                {{ __(':played/:total played', ['played' => $standing['played'], 'total' => $standing['total']]) }}
                            </span>
                        </div>

                        {{-- La barre suit le calendrier : un segment par journée. --}}
                        <div class="mt-2 flex gap-0.5" role="img"
                            aria-label="{{ __(':played of :total match days played', ['played' => $standing['played'], 'total' => $standing['total']]) }}">
                            @foreach ($standing['segments'] as $segment)
                                <span @class([
                                    'h-2 flex-1 rounded-sm',
                                    'bg-base-content/30' => $segment === 'past',
                                    'bg-error' => $segment === 'urgent',
                                    'bg-warning' => $segment === 'actionable',
                                    'bg-success' => $segment === 'confirmed',
                                    'bg-base-200' => $segment === 'future',
                                ])></span>
                            @endforeach
                        </div>

                        <div class="mt-2 flex items-center gap-1.5 text-xs font-semibold {{ $verdict[0] }}">
                            <x-icon :name="$verdict[1]" class="h-4 w-4 shrink-0" />
                            <span>{{ $verdict[2] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Ce qui demande une action, daté --}}
            @php
                $actionable = collect($weekSummary['week_rows'])
                    ->filter(fn (array $r): bool => in_array($r['status'], ['urgent', 'actionable'], true))
                    ->sortBy('starts_at')
                    ->values();
                $futureRows = collect($weekSummary['week_rows'])
                    ->reject(fn (array $r): bool => $r['is_past'] || in_array($r['status'], ['urgent', 'actionable'], true))
                    ->sortBy('starts_at')
                    ->values();
                $playedRows = collect($weekSummary['week_rows'])
                    ->filter(fn (array $r): bool => $r['is_past'])
                    ->sortByDesc('starts_at')
                    ->values();
            @endphp

            @if ($actionable->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-base-200 bg-base-100">
                    <div class="border-b border-base-200 bg-error/5 px-3 py-2 text-xs font-bold uppercase tracking-widest text-error">
                        {{ __('Needs attention') }} · {{ $actionable->count() }}
                    </div>
                    <div class="divide-y divide-base-200">
                        @foreach ($actionable as $row)
                            <div class="flex items-start gap-3 px-3 py-3">
                                <span @class([
                                    'w-1 shrink-0 self-stretch rounded-full',
                                    'bg-error' => $row['status'] === 'urgent',
                                    'bg-warning' => $row['status'] === 'actionable',
                                ]) aria-hidden="true"></span>
                                <div class="w-16 shrink-0">
                                    <div class="text-sm font-bold tabular-nums">{{ __('Day') }} {{ $matchDayMap[$row['wk']] ?? $row['wk'] }}</div>
                                    <div class="text-xs tabular-nums text-base-content/60">{{ $row['date'] }}</div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-bold">
                                        {{ $categoryLabels[$row['category']] ?? $row['category'] }}
                                        <span class="font-normal text-base-content/60">
                                            · {{ trans_choice(':count team|:count teams', count($row['cells']), ['count' => count($row['cells'])]) }}
                                        </span>
                                    </div>
                                    <div @class(['mt-0.5 text-xs', 'font-semibold text-error' => $row['status'] === 'urgent', 'text-base-content/70' => $row['status'] !== 'urgent'])>
                                        {{ trans_choice(':count team still to compose|:count teams still to compose', $row['to_compose'], ['count' => $row['to_compose']]) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="flex items-center gap-2 rounded-xl border border-success/30 bg-success/5 px-3 py-3 text-sm font-semibold text-success">
                    <x-icon name="o-check-circle" class="h-5 w-5 shrink-0" />
                    {{ __('Everything under control') }}
                </div>
            @endif

            {{-- La frise complète : chronologie, repliée par défaut --}}
            @if ($futureRows->isNotEmpty() || $playedRows->isNotEmpty())
                <div class="space-y-3">
                    @foreach ([
                        ['rows' => $futureRows, 'label' => __('Upcoming'), 'color' => 'blue'],
                        ['rows' => $playedRows, 'label' => __('Played match days'), 'color' => 'gray'],
                    ] as $fold)
                        @continue($fold['rows']->isEmpty())
                        <x-section-accordion
                            :label="$fold['label']"
                            :count="$fold['rows']->count()"
                            :color="$fold['color']"
                            :open="false">
                            <div class="divide-y divide-base-200 overflow-hidden rounded-xl border border-base-200 bg-base-100">
                                @foreach ($fold['rows'] as $row)
                                    <div class="flex items-center gap-3 px-3 py-2">
                                        <div class="w-16 shrink-0">
                                            <div class="text-sm font-bold tabular-nums">{{ __('Day') }} {{ $matchDayMap[$row['wk']] ?? $row['wk'] }}</div>
                                            <div class="text-xs tabular-nums text-base-content/60">{{ $row['date'] }}</div>
                                        </div>
                                        <span class="min-w-0 flex-1 text-sm">
                                            {{ $categoryLabels[$row['category']] ?? $row['category'] }}
                                            <span class="text-base-content/60">· {{ count($row['cells']) }}</span>
                                        </span>
                                        <span @class([
                                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-bold',
                                            'bg-success/10 text-success' => $row['status'] === 'confirmed',
                                            'bg-base-200 text-base-content/70' => $row['status'] !== 'confirmed',
                                        ])>{{ $statusLabel($row['status']) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </x-section-accordion>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ── LÉGENDE ──────────────────────────────────────────────────── --}}
        {{-- Elle décrit les pastilles de la grille : elle disparaît avec elle. --}}
        <div class="hidden flex-wrap items-center gap-x-4 gap-y-2 text-xs text-base-content/60 lg:flex">
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-success"></span>{{ __('Under control') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-warning"></span>{{ __('Ready to compose') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-error"></span>{{ __('Needs attention') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-base-300"></span>{{ __('Upcoming') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-base-300"></span>{{ __('Played') }}</span>
            <span class="hidden items-center gap-1.5 lg:flex"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-base-200"></span>{{ __('Rest week for this category') }}</span>
        </div>
    </div>
@endif
