<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header progress-indicator separator :subtitle="__('Manage team selections')" :title="__('Selections')">
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)" :show-search="false" :show-more="false" />
            <div class="hidden lg:block">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
        </x-slot:actions>
    </x-header>

    {{-- La saison entière : une ligne par équipe, une colonne par journée. C'est
         la matrice dont les deux modes ci-dessous lisent une ligne ou une colonne.
         Repliée par défaut — on vient d'abord ici pour composer, pas pour superviser. --}}
    @if ($isAdminOrCommittee && $weekSummary && $weekSummary['weeks'] !== [])
        <div class="mb-6">
            <x-section-accordion
                :label="__('Season overview')"
                :count="__(':ok of :total match days under control', ['ok' => $weekSummary['ok'], 'total' => $weekSummary['total']])"
                color="gray"
                :open="false"
                :uppercase="false">
                @include('pages::club-events.interclubs.⚡captain-selection._prep-score-widget', [
                    'weekSummary' => $weekSummary,
                    'matchDayMap' => $matchDayMap,
                    'isAdminOrCommittee' => true,
                ])
            </x-section-accordion>
        </div>
    @endif

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
            $dayIndex = array_search($selectedMatchDay, $matchDays, true);
            $prevDay = $dayIndex !== false && $dayIndex > 0 ? $matchDays[$dayIndex - 1] : null;
            $nextDay = $dayIndex !== false && $dayIndex < count($matchDays) - 1 ? $matchDays[$dayIndex + 1] : null;
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

                @if (count($matchDays) > 1)
                    <div class="flex items-center gap-2">
                        <x-button class="btn-sm btn-ghost border border-base-300" icon="o-chevron-left"
                            :disabled="$prevDay === null"
                            :aria-label="__('Previous match day')"
                            wire:click="selectDay({{ $prevDay ?? 'null' }})" />
                        <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 py-0.5">
                            @foreach ($matchDays as $day)
                                <button type="button" wire:click="selectDay({{ $day }})"
                                    @if ($day === $selectedMatchDay) aria-current="true" @endif
                                    @class([
                                        'shrink-0 rounded-full border px-3 py-1 text-sm font-bold tabular-nums transition-colors',
                                        'border-primary bg-primary/10 text-primary' => $day === $selectedMatchDay,
                                        'border-base-300 text-base-content/70 hover:border-primary/50' => $day !== $selectedMatchDay,
                                    ])>{{ $matchDayMap[$day] ?? $day }}</button>
                            @endforeach
                        </div>
                        <x-button class="btn-sm btn-ghost border border-base-300" icon="o-chevron-right"
                            :disabled="$nextDay === null"
                            :aria-label="__('Next match day')"
                            wire:click="selectDay({{ $nextDay ?? 'null' }})" />
                    </div>
                @endif
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
                        <div class="divide-y divide-base-200 overflow-hidden rounded-xl border border-base-300">
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
    {{-- ── FILTER DRAWER ÉQUIPES ──────────────────────────────────────── --}}
    {{-- Never wrap x-drawer in @if — x-teleport moves DOM to body, @if breaks Livewire morph --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-widest opacity-60">
                    {{ __('Season') }}
                </p>
                <x-select :options="$seasons_list" wire:model.live="selectedSeasonId" class="w-full" />
            </div>
            {{-- L'équipe n'est plus ici : elle détermine l'objet de la page, donc
                 c'est une navigation (DS-A). Elle vit au-dessus de la liste. --}}
            <div>
                <x-toggle :label="__('Show issues only')" wire:model.live="filterAlerts" />
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>

    {{-- ── DRAWER SÉLECTION (partagé avec le control center) ───────────── --}}
    <x-admin.club-events.interclubs.selection-drawer
        model="drawerSelection"
        :title="$drawerTitle"
        :subtitle="$drawerSubtitle"
        :roster="$roster"
        :selected-ids="$selectedPlayerIds"
        :max-players="$maxPlayers"
        :week-number="$drawerInterclub?->week_number"
        :can-search-substitute="$canSearchSubstitute"
        :search-results="$searchResults"
        :search-note="$searchNote"
        :search-term="$search" />

    {{-- ── CONFIRMATION : RELANCE DES DISPONIBILITÉS ──────────────────── --}}
    {{-- L'action envoie des e-mails à toute l'équipe : elle se confirme. --}}
    <x-confirm-modal model="availabilityRequestModal" :title="__('Request availability?')"
        :confirmLabel="__('Send the request')" confirmAction="requestAvailability"
        :open="$availabilityRequestModal">
        <p>{{ __('Every team member who has not answered yet will receive an email.') }}</p>
    </x-confirm-modal>

    {{-- ── MODAL LINEUP / MESSAGE ─────────────────────────────────────── --}}
    <x-app-modal separator :title="$isUpdateMode ? __('Update the team') : __('Notify the team')" wire:model="modalMessage" :open="$modalMessage">
        <div class="space-y-4">
            @if ($isUpdateMode)
                {{-- Diff summary: only added/removed players are notified --}}
                @if (! empty($pendingRemovedNames))
                    <div class="bg-error/5 border border-error/20 rounded-xl p-3">
                        <div class="mb-2 text-xs font-bold uppercase tracking-widest text-error/70">
                            {{ __('Removed — will always be notified') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($pendingRemovedNames as $name)
                                <x-badge class="badge-error badge-soft badge-sm font-bold" :value="$name" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! empty($pendingAddedNames))
                    <div class="bg-base-200/50 rounded-xl p-3">
                        <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-60">
                            {{ __('Added') }}
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($pendingAddedNames as $name)
                                <x-badge class="badge-primary badge-soft badge-sm font-bold" :value="$name" />
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (! $modalIsComplete)
                    <div class="rounded-xl border border-warning/30 bg-warning/5 p-3 text-xs text-warning-content">
                        <div class="flex items-center gap-1.5 font-semibold">
                            <x-icon name="o-exclamation-triangle" class="h-3.5 w-3.5" />
                            {{ __('Selection incomplete: only the removed players will be notified for now.') }}
                        </div>
                    </div>
                @endif

                <x-textarea
                    class="border-none bg-base-200/50 focus:ring-primary"
                    :label="__('Meetup info (optional)')"
                    :placeholder="__('E.g. Meet at 18:45 at the club entrance, bring your club shirt...')"
                    rows="4"
                    wire:model="captainMeetupInfo" />
            @else
                {{-- Selected lineup summary --}}
                @php
                    $selCount = count($selectedPlayerIds);
                    $maxP = $maxPlayers;
                @endphp

                <div class="bg-base-200/50 rounded-xl p-3">
                    <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-60">
                        {{ __('Selected lineup (:n/:max)', ['n' => $selCount, 'max' => $maxP]) }}
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($roster->filter(fn ($p) => in_array($p['id'], $selectedPlayerIds)) as $p)
                            <x-badge class="badge-primary badge-soft badge-sm font-bold" :value="$p['name']" />
                        @endforeach
                    </div>
                </div>

                <x-textarea
                    class="border-none bg-base-200/50 focus:ring-primary"
                    :label="__('Meetup info (optional)')"
                    :placeholder="__('E.g. Meet at 18:45 at the club entrance, bring your club shirt...')"
                    rows="4"
                    wire:model="captainMeetupInfo" />

                <div class="rounded-xl border border-info/20 bg-info/5 p-3 text-xs text-info">
                    <div class="flex items-center gap-1.5 font-semibold">
                        <x-icon name="o-information-circle" class="h-3.5 w-3.5" />
                        {{ __('All team members will be notified. Selected players receive a calendar invite (ICS).') }}
                    </div>
                </div>
            @endif
        </div>

        <x-slot:actions>
            <x-button class="btn-ghost" :label="__('Skip')" wire:click="skipSending" />
            <x-button class="btn-primary" icon="o-paper-airplane" :label="__('Send to team')"
                wire:click="sendLineupToTeam" />
        </x-slot:actions>
    </x-app-modal>
</div>
