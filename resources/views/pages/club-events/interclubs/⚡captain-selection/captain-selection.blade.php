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
                :count="__(':ok of :total match days under control', ['ok' => $weekSummary['ok'], 'total' => $weekSummary['total']])
                    . ($weekSummary['short_handed'] > 0
                        ? ' · ' . trans_choice('including :count short-handed fixture|including :count short-handed fixtures', $weekSummary['short_handed'], ['count' => $weekSummary['short_handed']])
                        : '')"
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

    {{-- La journée d'en haut : pour qui arbitre entre les équipes. Repliée, elle
         ne calcule que son compteur ; ouverte, elle rend les verdicts C.22. --}}
    @if ($canSeeDayAvailability)
        @php
            $shortTeamCount = $dayAvailability->sum(fn ($day): int => $day->teams->filter->isShort()->count());
            $freePlayerCount = $dayAvailability->sum(fn ($day): int => $day->players->whereNull('lineupTeamName')->count());
            $hasUncoveredTeam = $dayAvailability->contains(fn ($day): bool => $day->teams->contains(
                fn ($team): bool => $team->need === \App\Domains\Shared\Enums\TeamLineupNeed::UNCOVERED,
            ));
            $detailsGroups = $viewMode === 'day' ? $dayGroups : $matchGroups;
        @endphp
        <div class="mb-6">
            <x-section-accordion
                :label="__('Match day availability')"
                :count="trans_choice(':count short|:count short', $shortTeamCount, ['count' => $shortTeamCount])
                    . ' · ' . trans_choice(':count free|:count free', $freePlayerCount, ['count' => $freePlayerCount])"
                :color="$hasUncoveredTeam ? 'rose' : 'gray'"
                :open="$dayAvailabilityOpen"
                :uppercase="false"
                wire-toggle="toggleDayAvailability">
                @include('pages::club-events.interclubs.⚡captain-selection._day-availability')
            </x-section-accordion>
        </div>

        <x-section-accordion
            :label="__('Details and selections')"
            :count="collect($detailsGroups)->flatten(1)->count()"
            color="blue"
            :uppercase="false">
            @include('pages::club-events.interclubs.⚡captain-selection._details')
        </x-section-accordion>
    @else
        @include('pages::club-events.interclubs.⚡captain-selection._details')
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
        :fixture-id="$drawerInterclub?->id"
        :can-search-substitute="$canSearchSubstitute"
        :search-results="$searchResults"
        :search-note="$searchNote"
        :search-term="$search"
        :pool-rows="$poolRows"
        :pool-waiting="$poolWaiting"
        :pool-hidden-count="$poolHiddenCount"
        :pool-unranked-count="$poolUnrankedCount"
        :pool-maybe-count="$poolMaybeCount"
        :pool-maybe-teams="$poolMaybeTeams"
        :lineup-constraint="$lineupConstraint"
        walkover-action="designateWalkover"
        :walkover-id="$walkoverPlayerId"
        :readonly="$isReadOnly"
        :sent-at="$drawerSentAt"
        :minimum-players="$drawerInterclub?->minimumPlayers()" />

    {{-- ── CONFIRMATION : RELANCE DES DISPONIBILITÉS ──────────────────── --}}
    {{-- L'action envoie des e-mails à toute l'équipe : elle se confirme. --}}
    <x-confirm-modal model="availabilityRequestModal" :title="__('Request availability?')"
        :confirmLabel="__('Send the request')" confirmAction="requestAvailability"
        :open="$availabilityRequestModal">
        <p>{{ __('Every team member who has not answered yet will receive an email.') }}</p>
    </x-confirm-modal>

    {{-- ── MODAL LINEUP / MESSAGE ─────────────────────────────────────── --}}
    {{-- Le titre nomme la rencontre : une action qui engage une douzaine
         d'e-mails ne laisse pas deviner qui elle vise. --}}
    <x-app-modal separator
        :title="$isUpdateMode ? __('Update the team') : __('Notify the team')"
        :subtitle="$sendTargetLabel"
        wire:model="modalMessage" :open="$modalMessage">
        <div class="space-y-4">
            {{-- La crainte qui retient l'envoi : « et si ça change ? ». La mise à
                 jour ne prévient que les concernés — il suffit de le dire. --}}
            <p class="flex items-start gap-2 text-sm text-base-content/80" data-send-reassurance>
                <x-icon name="o-arrow-path" class="mt-0.5 h-4 w-4 shrink-0 text-base-content/50" />
                <span>{{ __('You can change it until match day: only the players added or removed will be told.') }}</span>
            </p>

            {{-- Ce que le capitaine vient de déclarer, et ce que cela coûte : la
                 dernière chose qu'il lit avant de convoquer l'équipe. --}}
            @if ($sendsShortHanded)
                <div class="rounded-xl border border-warning/40 bg-warning/5 p-3 text-sm">
                    <div class="flex items-center gap-1.5 font-semibold">
                        <x-icon name="o-exclamation-triangle" class="h-4 w-4 text-warning" />
                        {{ __('You will play with :n of :max.', ['n' => $sendPlayingCount, 'max' => $sendMaxPlayers]) }}
                    </div>
                    <p class="mt-1 text-xs text-base-content/80">
                        @if ($sendWalkoverName)
                            {{ __(':player goes on the sheet as walkover (WO).', ['player' => $sendWalkoverName]) }}
                        @endif
                        {{ __('The missing player\'s matches will be lost. The team will be told it plays short.') }}
                    </p>
                </div>
            @endif
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
                {{-- Les noms viennent de la compo enregistrée, pas de $roster :
                     $roster n'existe que si le tiroir est ouvert, et saveSelection()
                     vient de le fermer. Le bloc annonçait « 4/4 » sans un nom. --}}
                <div class="bg-base-200/50 rounded-xl p-3">
                    <div class="mb-2 text-xs font-bold uppercase tracking-widest opacity-60">
                        {{ __('Selected lineup (:n/:max)', ['n' => count($sendLineupNames), 'max' => $sendMaxPlayers]) }}
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($sendLineupNames as $name)
                            <x-badge class="badge-primary badge-soft badge-sm font-bold" :value="$name" />
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
            {{-- Le bouton dit ce qu'il fait. Sur une compo redevenue incomplète,
                 seuls les joueurs écartés sont prévenus — l'encart l'expliquait
                 en `text-xs` pendant que le bouton juste dessous promettait le
                 contraire. --}}
            @php
                $onlyRemoved = $isUpdateMode && ! $modalIsComplete;
                $sendLabel = $onlyRemoved
                    ? trans_choice('Notify the removed player|Notify the :count removed players', count($pendingRemovedNames), ['count' => count($pendingRemovedNames)])
                    : __('Send to team');
            @endphp
            <x-button class="btn-ghost" :label="__('Send later')" spinner="skipSending" wire:click="skipSending" />
            <x-button class="btn-primary" icon="o-paper-airplane" :label="$sendLabel"
                spinner="sendLineupToTeam" wire:click="sendLineupToTeam" />
        </x-slot:actions>
    </x-app-modal>
</div>
