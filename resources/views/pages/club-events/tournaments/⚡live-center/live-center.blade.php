{{--
    Plus de max-w-6xl : la régie met les tables et la file côte à côte, et les
    368 px que le bridage laissait vides sur un 1920 sont exactement ce qui
    permet à la colonne de droite d'exister. La clôture, plus proche d'un
    formulaire, garde sa propre largeur de lecture.
--}}
<div class="p-4">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    @include('components.admin.club-events.tournaments.partials.shared.tournament-header', [
        'tournament' => $tournament,
        'shownPhase' => $this->shownPhase,
        'banner' => $this->banner,
    ])

    {{--
        Jour J lancé : la régie est la section principale ; poules, tableau et
        classements se résument en cartes au-dessus et s'ouvrent en plein écran.
        Bilan : la clôture.
    --}}
    @if ($activeTab === 'closure' && $this->canManageTournament)
        <div class="mx-auto max-w-5xl">
            @include('admin.club-events.tournaments.partials.live.tabs.closure')
        </div>

    @elseif ($activeTab === 'control-room' && $this->canManageTournament)
        @php
            $poolTotal = \App\Domains\Competitions\Tournament\Models\TournamentMatch::where('tournament_id', $tournament->id)->whereNotNull('pool_id')->count();
            $poolPlayed = $this->playedPoolMatches->flatten(1)->count();
        @endphp
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" data-summary-cards>
            @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                'key' => 'pools', 'title' => __('Pools'), 'value' => $poolPlayed . ' / ' . $poolTotal,
                'caption' => __('matches played'), 'alert' => null,
                'button' => __('See the pools'), 'wire' => "\$set('activeTab', 'pools')",
            ])
            @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                'key' => 'bracket', 'title' => __('Bracket'),
                'value' => ! $this->bracketExists ? __('Not created yet') : ($this->bracketPhaseComplete ? __('Finished') : __('In progress')),
                'caption' => null, 'alert' => null,
                'button' => __('See the bracket'), 'wire' => "\$set('activeTab', 'bracket')",
            ])
            @include('components.admin.club-events.tournaments.partials.shared.summary-card', [
                'key' => 'rankings', 'title' => __('Rankings'), 'value' => trans_choice(':count pool|:count pools', $tournament->pools->count(), ['count' => $tournament->pools->count()]),
                'caption' => null, 'alert' => null,
                'button' => __('See the rankings'), 'wire' => "\$set('activeTab', 'rankings')",
            ])
        </div>
        @include('admin.club-events.tournaments.partials.live.tabs.control-room')

    @else
        @if ($this->canManageTournament)
            <x-button :label="__('Back to the control room')" icon="o-arrow-left" class="btn-ghost btn-sm"
                wire:click="$set('activeTab', 'control-room')" data-back />
        @else
            <div class="flex flex-wrap gap-1" role="tablist">
                @foreach (['pools' => __('Pools'), 'bracket' => __('Bracket'), 'rankings' => __('Rankings')] as $view => $viewLabel)
                    <x-button :label="$viewLabel" wire:click="$set('activeTab', '{{ $view }}')"
                        :class="$activeTab === $view ? 'btn-sm btn-primary' : 'btn-sm btn-ghost'" />
                @endforeach
            </div>
        @endif

        @if ($activeTab === 'bracket')
            @include('admin.club-events.tournaments.partials.live.tabs.bracket', ['manage' => $this->canManageTournament])
        @elseif ($activeTab === 'rankings')
            @include('admin.club-events.tournaments.partials.live.tabs.rankings')
        @else
            @include('admin.club-events.tournaments.partials.live.tabs.pools', ['manage' => $this->canManageTournament])
        @endif
    @endif

    {{-- ── Drawers (committee / admin only) ──────────────────────────── --}}
    @if ($this->canManageTournament)
        @include('admin.club-events.tournaments.partials.live.drawers.score-entry')
        @include('admin.club-events.tournaments.partials.live.drawers.launch-match')
    @endif

</div>
