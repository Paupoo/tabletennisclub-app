<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="[['label' => 'Dashboard']]" />
    </x-slot:breadcrumbs>

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
        <div class="space-y-2">
            @foreach($alerts as $alert)
            @php
                $alertClass = match($alert['type']) {
                    'warning' => 'alert-warning',
                    'error'   => 'alert-error',
                    'info'    => 'alert-info',
                    default   => 'alert-warning',
                };
            @endphp
            <a href="{{ $alert['route'] }}" role="alert" class="alert {{ $alertClass }} py-2.5 flex items-center gap-2 hover:opacity-80 transition-opacity">
                <x-icon name="{{ $alert['icon'] }}" class="w-4 h-4 shrink-0" />
                <span class="text-sm font-medium">{{ $alert['label'] }}</span>
                <x-icon name="o-arrow-right" class="w-3.5 h-3.5 ml-auto shrink-0 opacity-60" />
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
                    color="emerald">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($memberTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>

                @if($showSecretary)
                @php
                    $secretaryTiles = [
                        ['icon' => 'o-users',        'label' => 'Membres',      'sub' => 'Gestion des membres',    'href' => route('admin.users.index'),            'color' => 'primary'],
                        ['icon' => 'o-user-plus',     'label' => 'Inscriptions', 'sub' => 'Nouvelles demandes',     'href' => route('admin.users.registrations'),    'color' => 'primary'],
                        ['icon' => 'o-newspaper',     'label' => 'News',         'sub' => 'Articles & actualités',  'href' => route('admin.website.articles.index'), 'color' => 'primary'],
                        ['icon' => 'o-envelope',      'label' => 'Contacts',     'sub' => 'Messages reçus',         'href' => route('admin.website.contacts.index'), 'color' => 'primary'],
                        ['icon' => 'o-calendar-days', 'label' => 'Réunions',     'sub' => 'Comptes rendus',         'href' => route('admin.meetings.index'),         'color' => 'primary'],
                        ['icon' => 'o-calendar',      'label' => 'Événements',   'sub' => 'Activités planifiées',   'href' => route('admin.website.events.index'),   'color' => 'primary'],
                        ['icon' => 'o-cog-6-tooth',   'label' => 'Configuration','sub' => 'Club & paramètres',      'href' => route('admin.club-info'),              'color' => 'primary'],
                    ];
                @endphp
                <x-section-accordion
                    :label="__('Secretary')"
                    :count="count($secretaryTiles) . ' accès'"
                    color="blue">
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
                        ['icon' => 'o-banknotes',        'label' => 'Paiements',    'sub' => 'Suivi des paiements',  'href' => route('admin.treasury.payments'),     'color' => 'secondary'],
                        ['icon' => 'o-credit-card',      'label' => 'Transactions', 'sub' => 'Relevés bancaires',    'href' => route('admin.treasury.transactions'), 'color' => 'secondary'],
                        ['icon' => 'o-building-library', 'label' => 'Caisse',       'sub' => 'Registre de caisse',   'href' => route('admin.treasury.cash'),         'color' => 'secondary'],
                        ['icon' => 'o-calendar-days',    'label' => 'Saisons',      'sub' => 'Gestion des périodes', 'href' => route('admin.seasons.index'),         'color' => 'secondary'],
                    ];
                @endphp
                <x-section-accordion
                    :label="__('Treasurer')"
                    :count="count($treasurerTiles) . ' accès'"
                    color="amber">
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
                        ['icon' => 'o-trophy',                   'label' => 'Équipes',        'sub' => 'Gestion des équipes',    'href' => route('admin.interclubs.teams'),             'color' => 'error'],
                        ['icon' => 'o-globe-alt',                'label' => 'Interclubs',     'sub' => 'Calendrier & matchs',    'href' => route('admin.interclubs.interclubs'),        'color' => 'error'],
                        ['icon' => 'o-clipboard-document-check', 'label' => 'Sélections',     'sub' => "Compositions d'équipe",  'href' => route('admin.interclubs.captain-selection'), 'color' => 'error'],
                        ['icon' => 'o-chart-bar',                'label' => 'Résultats',      'sub' => 'Scores & classements',   'href' => route('admin.interclubs.results'),           'color' => 'error'],
                        ['icon' => 'o-adjustments-horizontal',   'label' => 'Control center', 'sub' => 'Suivi en temps réel',    'href' => route('admin.interclubs.control-center'),    'color' => 'error'],
                    ];
                @endphp
                <x-section-accordion
                    :label="__('Captain / Selector')"
                    :count="count($captainTiles) . ' accès'"
                    color="rose">
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
                        ['icon' => 'o-users',             'label' => 'Membres',       'sub' => 'Vue globale',             'href' => route('admin.users.index'),       'color' => 'neutral'],
                        ['icon' => 'o-building-office-2', 'label' => 'Salles',        'sub' => 'Installations sportives', 'href' => route('admin.rooms.index'),       'color' => 'neutral'],
                        ['icon' => 'o-clock',             'label' => 'Entraînements', 'sub' => 'Séances programmées',     'href' => route('admin.trainings.index'),   'color' => 'neutral'],
                        ['icon' => 'o-calendar-days',     'label' => 'Saisons',       'sub' => 'Gestion des périodes',    'href' => route('admin.seasons.index'),     'color' => 'neutral'],
                        ['icon' => 'o-trophy',            'label' => 'Tournois',      'sub' => 'Compétitions internes',   'href' => route('admin.tournaments.index'), 'color' => 'neutral'],
                        ['icon' => 'o-bell',              'label' => 'Notifications', 'sub' => 'Envoi de messages',       'href' => route('notifications.index'),     'color' => 'neutral'],
                    ];
                @endphp
                <x-section-accordion
                    :label="__('Committee')"
                    :count="count($committeeTiles) . ' accès'"
                    color="violet">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($committeeTiles as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>
                @endif

            </div>

            {{-- RIGHT: Activity feed --}}
            <div class="lg:col-span-1 space-y-3">

                <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">{{ __('Recent activity') }}</p>

                <div class="bg-base-100 rounded-xl border border-base-200 shadow-sm divide-y divide-base-200">
                    @forelse($recentActivity as $item)
                    @php
                        $feedCfg = match($item['type']) {
                            'member'  => ['icon' => 'o-user',      'color' => 'text-primary'],
                            'contact' => ['icon' => 'o-envelope',  'color' => 'text-info'],
                            'match'   => ['icon' => 'o-globe-alt', 'color' => 'text-error'],
                            'payment' => ['icon' => 'o-banknotes', 'color' => 'text-warning'],
                            default   => ['icon' => 'o-bell',      'color' => 'text-base-content/50'],
                        };
                    @endphp
                    <div class="flex items-start gap-3 px-3 py-2.5 hover:bg-base-200/50 transition-colors">
                        <div class="bg-base-200 rounded-full p-1.5 shrink-0 mt-0.5 {{ $feedCfg['color'] }}">
                            <x-icon name="{{ $feedCfg['icon'] }}" class="w-3.5 h-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-base-content leading-snug">{{ $item['label'] }}</p>
                        </div>
                        <span class="text-xs text-base-content/30 shrink-0 tabular-nums whitespace-nowrap">{{ $item['time'] }}</span>
                    </div>
                    @empty
                    <div class="px-4 py-6 text-center text-sm text-base-content/40">
                        Aucune activité récente
                    </div>
                    @endforelse
                </div>

                <a href="{{ route('admin.users.index') }}" class="block text-xs text-center text-base-content/40 hover:text-base-content/70 transition-colors py-1">
                    Voir tout →
                </a>

            </div>

        </div>

    </div>
</x-app-layout>
