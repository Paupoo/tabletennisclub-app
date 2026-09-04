<x-app-layout>
    <div class="p-4 sm:p-6 space-y-5">

        {{-- HEADER --}}
        <div class="flex items-center gap-3 text-sm text-base-content/50">
            <span class="font-semibold text-base-content">{{ Auth::user()->first_name }} {{ Auth::user()->last_name }}</span>
            @if(Auth::user()->committee_role)
                <span class="text-base-content/30">·</span>
                <span>{{ Auth::user()->committee_role->label() }}</span>
            @endif
            <span class="text-base-content/30">·</span>
            <span>{{ now()->translatedFormat('l j F Y') }}</span>
        </div>

        {{-- ALERTS (conditional — hidden if no data) --}}
        @if(count($alerts) > 0)
        <div class="flex flex-wrap gap-1.5">
            @foreach($alerts as $alert)
            @php
                $pillClass = match($alert['type']) {
                    'error'   => 'bg-error/10 text-error border-error/20 hover:bg-error/20',
                    'warning' => 'bg-warning/10 text-warning-content border-warning/20 hover:bg-warning/20',
                    'info'    => 'bg-info/10 text-info border-info/20 hover:bg-info/20',
                    default   => 'bg-warning/10 text-warning-content border-warning/20 hover:bg-warning/20',
                };
            @endphp
            <a href="{{ $alert['route'] }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border {{ $pillClass }} transition-colors">
                <x-icon name="{{ $alert['icon'] }}" class="w-3 h-3 shrink-0" />
                {{ $alert['label'] }}
                <x-icon name="o-arrow-right" class="w-2.5 h-2.5 opacity-40 shrink-0" />
            </a>
            @endforeach
        </div>
        @endif

        {{-- MAIN: Navigation + Feed --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- LEFT: Navigation accordions (role-filtered) --}}
            <div class="lg:col-span-2 space-y-4">

                {{-- MON ESPACE (all users) --}}
                <x-section-accordion
                    label="Mon espace"
                    :count="count($memberTiles) . ' accès'"
                    color="gray">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($memberTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>

                @if($showSecretary)
                @php
                    $secretaryTiles = [
                        ['icon' => 'o-users',         'label' => 'Membres',       'sub' => 'Gestion des membres',   'href' => route('admin.users.index')],
                        ['icon' => 'o-user-plus',     'label' => 'Inscriptions',  'sub' => 'Nouvelles demandes',    'href' => route('admin.users.registrations')],
                        ['icon' => 'o-newspaper',     'label' => 'News',          'sub' => 'Articles & actualités', 'href' => route('admin.website.articles.index'), 'feature' => 'website'],
                        ['icon' => 'o-envelope',      'label' => 'Contacts',      'sub' => 'Messages reçus',        'href' => route('admin.website.contacts.index'), 'feature' => 'contacts'],
                        ['icon' => 'o-calendar-days', 'label' => 'Réunions',      'sub' => 'Comptes rendus',        'href' => route('admin.meetings.index'),         'feature' => 'meetings'],
                        ['icon' => 'o-calendar',      'label' => 'Événements',    'sub' => 'Activités planifiées',  'href' => route('admin.website.events.index'),   'feature' => 'website'],
                        ['icon' => 'o-cog-6-tooth',   'label' => 'Configuration', 'sub' => 'Club & paramètres',    'href' => route('admin.club-info')],
                    ];
                    $secretaryTiles = array_filter($secretaryTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
                @endphp
                <x-section-accordion
                    :label="__('Secretary')"
                    :count="count($secretaryTiles) . ' accès'"
                    color="gray">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($secretaryTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>
                @endif

                @if($showTreasurer)
                @php
                    $treasurerTiles = [
                        ['icon' => 'o-banknotes',        'label' => 'Paiements',    'sub' => 'Suivi des paiements',  'href' => route('admin.treasury.payments'),     'feature' => 'treasury'],
                        ['icon' => 'o-credit-card',      'label' => 'Transactions', 'sub' => 'Relevés bancaires',    'href' => route('admin.treasury.transactions'), 'feature' => 'treasury'],
                        ['icon' => 'o-building-library', 'label' => 'Caisse',       'sub' => 'Registre de caisse',   'href' => route('admin.treasury.cash'),         'feature' => 'cash_register'],
                        ['icon' => 'o-calendar-days',    'label' => 'Saisons',      'sub' => 'Gestion des périodes', 'href' => route('admin.seasons.index')],
                    ];
                    $treasurerTiles = array_filter($treasurerTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
                @endphp
                <x-section-accordion
                    :label="__('Treasurer')"
                    :count="count($treasurerTiles) . ' accès'"
                    color="gray">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($treasurerTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>
                @endif

                @if($showCaptain)
                @php
                    $captainTiles = [
                        ['icon' => 'o-trophy',                   'label' => 'Équipes',        'sub' => 'Gestion des équipes',   'href' => route('admin.interclubs.teams'),             'feature' => 'interclubs'],
                        ['icon' => 'o-globe-alt',                'label' => 'Interclubs',     'sub' => 'Calendrier & matchs',   'href' => route('admin.interclubs.interclubs'),        'feature' => 'interclubs'],
                        ['icon' => 'o-clipboard-document-check', 'label' => 'Sélections',     'sub' => "Compositions d'équipe", 'href' => route('admin.interclubs.captain-selection'), 'feature' => 'interclubs'],
                        ['icon' => 'o-chart-bar',                'label' => 'Résultats',      'sub' => 'Scores & classements',  'href' => route('admin.interclubs.results'),           'feature' => 'interclubs'],
                    ];
                    $captainTiles = array_filter($captainTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
                @endphp
                <x-section-accordion
                    :label="__('Captain / Selector')"
                    :count="count($captainTiles) . ' accès'"
                    color="gray">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($captainTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>
                @endif

                @if($showCommittee)
                @php
                    $committeeTiles = [
                        ['icon' => 'o-users',             'label' => 'Membres',       'sub' => 'Vue globale',             'href' => route('admin.users.index')],
                        ['icon' => 'o-building-office-2', 'label' => 'Salles',        'sub' => 'Installations sportives', 'href' => route('admin.rooms.index')],
                        ['icon' => 'o-clock',             'label' => 'Entraînements', 'sub' => 'Séances programmées',     'href' => route('admin.trainings.index'),   'feature' => 'trainings'],
                        ['icon' => 'o-calendar-days',     'label' => 'Saisons',       'sub' => 'Gestion des périodes',    'href' => route('admin.seasons.index')],
                        ['icon' => 'o-trophy',            'label' => 'Tournois',      'sub' => 'Compétitions internes',   'href' => route('admin.tournaments.index'), 'feature' => 'tournaments'],
                    ];
                    $committeeTiles = array_filter($committeeTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
                @endphp
                <x-section-accordion
                    :label="__('Committee')"
                    :count="count($committeeTiles) . ' accès'"
                    color="gray">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($committeeTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>
                @endif

            </div>

            {{-- RIGHT: Agenda — one card per kind of object, already filtered.
                 The builder decided what this reader may see and where they may
                 go, so nothing here asks a permission: a block that arrived is a
                 block to render, and a null seeAllRoute is a screen out of reach. --}}
            <div class="lg:col-span-1 space-y-4">

                @foreach($agendaBlocks as $block)
                <div class="space-y-2" data-agenda-block="{{ $block->key }}">

                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">{{ $block->label }}</p>

                    <div class="bg-base-100 rounded-xl border border-base-300 shadow-sm divide-y divide-base-200">

                        {{-- A block looks forward; its lead line looks back. The
                             tinted ground, not merely the divider, is what keeps a
                             played match from reading as one still to come. --}}
                        @if($block->lead)
                        <div class="px-3 py-2.5 flex items-start justify-between gap-2 bg-base-200/50">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-base-content leading-snug">{{ $block->lead->label }}</p>
                                @if($block->lead->sub)
                                <p class="text-xs text-base-content/40 mt-0.5">{{ $block->lead->sub }}</p>
                                @endif
                            </div>
                            @if($block->lead->badge)
                            <span class="shrink-0 rounded-full border border-base-300 bg-base-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-base-content/70">{{ $block->lead->badge }}</span>
                            @endif
                        </div>
                        @endif

                        @foreach($block->rows as $row)
                        <div class="px-3 py-2.5 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-base-content leading-snug">{{ $row->label }}</p>
                                @if($row->sub)
                                <p class="text-xs text-base-content/40 mt-0.5">{{ $row->sub }}</p>
                                @endif
                            </div>
                            @if($row->badge)
                            <span class="shrink-0 rounded-full border border-base-300 px-2 py-0.5 text-xs font-medium text-base-content/50">{{ $row->badge }}</span>
                            @endif
                        </div>
                        @endforeach

                    </div>

                    @if($block->seeAllRoute)
                    <a href="{{ $block->seeAllRoute }}" class="block text-xs text-center text-base-content/40 hover:text-base-content/70 transition-colors py-1">
                        {{ __('See all') }} →
                    </a>
                    @endif

                </div>
                @endforeach

            </div>

        </div>

    </div>
</x-app-layout>
