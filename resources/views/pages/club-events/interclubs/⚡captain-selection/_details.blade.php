{{-- Le détail des rencontres : le sélecteur de sens de lecture, puis les
     quatre sections de statut. Enveloppé dans un accordéon pour qui voit aussi
     la journée d'en haut ; rendu nu pour un capitaine. --}}
@if ($teamsData->isEmpty())
    <x-empty-state icon="o-user-group" :heading="__('No team assigned')"
        :message="__('You are not captain of any team this season.')" />
@else

    {{-- ── BANDEAU : LES AUTRES ÉQUIPES ───────────────────────────── --}}
    {{-- Les matchs urgents de l'équipe affichée sont des lignes dans la liste
         ci-dessous ; les répéter ici ne faisait que dire deux fois la même
         chose. Le bandeau ne sert plus qu'à router vers les autres équipes. --}}
    @if ($viewMode === 'team' && $alertMatches->isNotEmpty())
        <div class="mb-6 rounded-xl border border-error/30 bg-error/5 p-4">
            <div class="mb-3 flex items-center gap-2">
                <x-icon name="o-exclamation-triangle" class="h-4 w-4 text-error" />
                <span class="text-sm font-bold text-error">
                    {{ trans_choice(':count urgent match in your other teams|:count urgent matches in your other teams', $alertMatches->count(), ['count' => $alertMatches->count()]) }}
                </span>
            </div>
            {{-- Plafonné : à neuf équipes, le bandeau affichait vingt et une
                 cartes et repoussait la liste hors de l'écran. --}}
            <div class="flex flex-wrap gap-3">
                @foreach ($alertMatches->take(6) as $am)
                    <button
                        type="button"
                        wire:click="openSelection({{ $am['id'] }})"
                        class="flex items-center gap-3 rounded-lg border border-error/30 bg-base-100 px-3 py-2 text-left text-xs transition-all hover:border-error/60 hover:shadow-sm">
                        <div class="flex flex-col">
                            <span class="font-bold">{{ $am['team_name'] }} vs {{ $am['opponent'] }}</span>
                            <span class="text-base-content/60">{{ $am['date'] }} · {{ __('Match day') }} {{ $matchDayMap[$am['wk']] ?? $am['wk'] }}</span>
                        </div>
                        <div class="flex items-center gap-1 text-error">
                            <x-icon name="o-user-group" class="h-3.5 w-3.5" />
                            <span class="font-bold">{{ $am['available_count'] }}/{{ $am['max_players'] }}</span>
                        </div>
                    </button>
                @endforeach
                @if ($alertMatches->count() > 6)
                    <span class="self-center text-xs text-base-content/70">
                        {{ __('+:count more', ['count' => $alertMatches->count() - 6]) }}
                    </span>
                @endif
            </div>
        </div>
    @endif

    <x-admin.shared.filter-chips :chips="$filterChips" />

    {{-- ── SENS DE LECTURE + IDENTITÉ (DS-A) ──────────────────────── --}}
    {{-- Une saison est une matrice équipe × journée. On en lit une ligne
         (une équipe, toutes ses journées) ou une colonne (une journée,
         toutes les équipes). Le sélecteur ne s'affiche que s'il y a
         plusieurs équipes à lire — sinon il n'y a qu'un sens possible. --}}
    @php
        $isDayMode = $viewMode === 'day';
        $groups = $isDayMode ? $dayGroups : $matchGroups;
        $hasAnyMatch = collect($groups)->flatten(1)->isNotEmpty();
                    $sections = [
            ['key' => 'todo',       'label' => __('To do'),         'color' => 'rose',    'open' => true],
            ['key' => 'controlled', 'label' => __('Under control'), 'color' => 'emerald', 'open' => true],
            ['key' => 'upcoming',   'label' => __('Upcoming'),      'color' => 'blue',    'open' => true],
            ['key' => 'played',     'label' => __('Played matches'), 'color' => 'gray',    'open' => false],
        ];
    @endphp

    @if (count($teams_for_switcher) > 1)
        <div class="mb-4 inline-flex rounded-full border border-base-300 p-0.5" role="group"
            aria-label="{{ __('Reading direction') }}">
            <button type="button" wire:click="setViewMode('team')"
                @if (! $isDayMode) aria-current="true" @endif
                @class([
                    'rounded-full px-4 py-1.5 text-sm font-bold transition-colors',
                    'bg-primary text-primary-content' => ! $isDayMode,
                    'text-base-content/70 hover:text-base-content' => $isDayMode,
                ])>{{ __('By team') }}</button>
            <button type="button" wire:click="setViewMode('day')"
                @if ($isDayMode) aria-current="true" @endif
                @class([
                    'rounded-full px-4 py-1.5 text-sm font-bold transition-colors',
                    'bg-primary text-primary-content' => $isDayMode,
                    'text-base-content/70 hover:text-base-content' => ! $isDayMode,
                ])>{{ __('By match day') }}</button>
        </div>
    @endif

    @if ($isDayMode)
        {{-- ── COLONNE : UNE JOURNÉE, TOUTES LES ÉQUIPES ───────────── --}}
        <div class="mb-4 flex flex-col gap-3">
            <div>
                <h2 class="text-lg font-bold">
                    {{ __('Match day') }} {{ $matchDayMap[$selectedMatchDay] ?? $selectedMatchDay ?? '—' }}
                </h2>
                <p class="text-sm text-base-content/60">
                    {{ trans_choice(':count team|:count teams', collect($groups)->flatten(1)->count(), ['count' => collect($groups)->flatten(1)->count()]) }}
                </p>
            </div>

            @include('pages::club-events.interclubs.⚡captain-selection._day-picker')
        </div>
    @elseif ($selectedTeamData)
        {{-- ── LIGNE : UNE ÉQUIPE, TOUTES SES JOURNÉES ────────────── --}}
        <div class="mb-4 flex flex-col gap-3">
            <div>
                <h2 class="text-lg font-bold">{{ __('Team') }} {{ $selectedTeamData['name'] }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Division') }} {{ $selectedTeamData['division'] }}
                    @if ($selectedTeamData['captain_name'])
                        <span aria-hidden="true">·</span>
                        {{ __('Captain') }} : {{ $selectedTeamData['captain_name'] }}
                    @endif
                </p>
            </div>

            @if (count($teams_for_switcher) > 1)
                <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                    @foreach ($teams_for_switcher as $t)
                        <button type="button" wire:click="selectTeam({{ $t['id'] }})"
                            @if ($t['id'] === $selectedTeamId) aria-current="true" @endif
                            @class([
                                'shrink-0 whitespace-nowrap rounded-full border px-4 py-1.5 text-sm font-bold transition-colors',
                                'border-primary bg-primary/10 text-primary' => $t['id'] === $selectedTeamId,
                                'border-base-300 text-base-content/70 hover:border-primary/50' => $t['id'] !== $selectedTeamId,
                            ])>
                            {{ __('Team') }} {{ $t['label'] }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- ── LISTE PAR SECTIONS, IDENTIQUE DANS LES DEUX SENS ────────── --}}
    @if (! $hasAnyMatch)
        <x-empty-state icon="o-calendar" :heading="__('No matches scheduled.')" />
    @else
        <div class="space-y-8">
            @foreach ($sections as $section)
                @continue(empty($groups[$section['key']]))
                <x-section-accordion
                    :label="$section['label']"
                    :count="count($groups[$section['key']])"
                    :color="$section['color']"
                    :open="$section['open']"
                    wire:key="section-{{ $viewMode }}-{{ $isDayMode ? $selectedMatchDay : $selectedTeamId }}-{{ $section['key'] }}">
                    {{-- Pas d'overflow-hidden : il rognait le panneau du menu « Plus »
                         des lignes. Les coins s'arrondissent sur les lignes elles-mêmes. --}}
                    <div class="divide-y divide-base-200 rounded-xl border border-base-300 [&>[data-match-row]:first-child]:rounded-t-xl [&>[data-match-row]:last-child]:rounded-b-xl">
                        @foreach ($groups[$section['key']] as $ic)
                            @include('pages::club-events.interclubs.⚡captain-selection._match-row', [
                                'ic' => $ic,
                                'mode' => $viewMode,
                                'matchDayMap' => $matchDayMap,
                            ])
                        @endforeach
                    </div>
                </x-section-accordion>
            @endforeach
        </div>
    @endif
@endif
