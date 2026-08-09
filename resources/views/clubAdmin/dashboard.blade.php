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
                        ['icon' => 'o-users',         'label' => 'Membres',       'sub' => 'Gestion des membres',   'href' => route('admin.users.index'),            'color' => 'blue'],
                        ['icon' => 'o-user-plus',     'label' => 'Inscriptions',  'sub' => 'Nouvelles demandes',    'href' => route('admin.users.registrations'),    'color' => 'emerald'],
                        ['icon' => 'o-newspaper',     'label' => 'News',          'sub' => 'Articles & actualités', 'href' => route('admin.website.articles.index'), 'color' => 'violet', 'feature' => 'website'],
                        ['icon' => 'o-envelope',      'label' => 'Contacts',      'sub' => 'Messages reçus',        'href' => route('admin.website.contacts.index'), 'color' => 'cyan', 'feature' => 'contacts'],
                        ['icon' => 'o-calendar-days', 'label' => 'Réunions',      'sub' => 'Comptes rendus',        'href' => route('admin.meetings.index'),         'color' => 'amber', 'feature' => 'meetings'],
                        ['icon' => 'o-calendar',      'label' => 'Événements',    'sub' => 'Activités planifiées',  'href' => route('admin.website.events.index'),   'color' => 'orange', 'feature' => 'website'],
                        ['icon' => 'o-cog-6-tooth',   'label' => 'Configuration', 'sub' => 'Club & paramètres',    'href' => route('admin.club-info'),              'color' => 'slate'],
                    ];
                    $secretaryTiles = array_filter($secretaryTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
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
                        ['icon' => 'o-banknotes',        'label' => 'Paiements',    'sub' => 'Suivi des paiements',  'href' => route('admin.treasury.payments'),     'color' => 'emerald', 'feature' => 'treasury'],
                        ['icon' => 'o-credit-card',      'label' => 'Transactions', 'sub' => 'Relevés bancaires',    'href' => route('admin.treasury.transactions'), 'color' => 'blue', 'feature' => 'treasury'],
                        ['icon' => 'o-building-library', 'label' => 'Caisse',       'sub' => 'Registre de caisse',   'href' => route('admin.treasury.cash'),         'color' => 'amber', 'feature' => 'cash_register'],
                        ['icon' => 'o-calendar-days',    'label' => 'Saisons',      'sub' => 'Gestion des périodes', 'href' => route('admin.seasons.index'),         'color' => 'violet'],
                    ];
                    $treasurerTiles = array_filter($treasurerTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
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
                        ['icon' => 'o-trophy',                   'label' => 'Équipes',        'sub' => 'Gestion des équipes',   'href' => route('admin.interclubs.teams'),             'color' => 'rose', 'feature' => 'interclubs'],
                        ['icon' => 'o-globe-alt',                'label' => 'Interclubs',     'sub' => 'Calendrier & matchs',   'href' => route('admin.interclubs.interclubs'),        'color' => 'blue', 'feature' => 'interclubs'],
                        ['icon' => 'o-clipboard-document-check', 'label' => 'Sélections',     'sub' => "Compositions d'équipe", 'href' => route('admin.interclubs.captain-selection'), 'color' => 'indigo', 'feature' => 'interclubs'],
                        ['icon' => 'o-chart-bar',                'label' => 'Résultats',      'sub' => 'Scores & classements',  'href' => route('admin.interclubs.results'),           'color' => 'amber', 'feature' => 'interclubs'],
                    ];
                    $captainTiles = array_filter($captainTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
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
                        ['icon' => 'o-users',             'label' => 'Membres',       'sub' => 'Vue globale',             'href' => route('admin.users.index'),       'color' => 'blue'],
                        ['icon' => 'o-building-office-2', 'label' => 'Salles',        'sub' => 'Installations sportives', 'href' => route('admin.rooms.index'),       'color' => 'emerald'],
                        ['icon' => 'o-clock',             'label' => 'Entraînements', 'sub' => 'Séances programmées',     'href' => route('admin.trainings.index'),   'color' => 'teal', 'feature' => 'trainings'],
                        ['icon' => 'o-calendar-days',     'label' => 'Saisons',       'sub' => 'Gestion des périodes',    'href' => route('admin.seasons.index'),     'color' => 'violet'],
                        ['icon' => 'o-trophy',            'label' => 'Tournois',      'sub' => 'Compétitions internes',   'href' => route('admin.tournaments.index'), 'color' => 'amber', 'feature' => 'tournaments'],
                    ];
                    $committeeTiles = array_filter($committeeTiles, fn (array $t): bool => ! isset($t['feature']) || \App\Domains\Shared\Enums\Feature::from($t['feature'])->enabled());
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
                    <div class="px-3 py-2.5 hover:bg-base-200/40 transition-colors">
                        <p class="text-xs font-medium text-base-content leading-snug">{{ $item['label'] }}</p>
                        <p class="text-[10px] text-base-content/40 mt-0.5">{{ $item['time'] }}</p>
                    </div>
                    @empty
                    <div class="px-4 py-6 text-center text-xs text-base-content/40">
                        Aucune activité récente
                    </div>
                    @endforelse
                </div>

                <a href="{{ route('admin.users.index') }}" class="block text-xs text-center text-base-content/40 hover:text-base-content/70 transition-colors py-1">
                    Voir tout →
                </a>

                {{-- PROCHAINS ENTRAÎNEMENTS --}}
                @if(count($upcomingTrainings) > 0)
                <div class="space-y-2 pt-1">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">Entraînements</p>
                    <div class="bg-base-100 rounded-xl border border-base-200 shadow-sm divide-y divide-base-200">
                        @foreach($upcomingTrainings as $training)
                        <div class="px-3 py-2.5">
                            <p class="text-xs font-medium text-base-content leading-snug">{{ $training['label'] }}</p>
                            @if($training['sub'])
                            <p class="text-[10px] text-base-content/40 mt-0.5">{{ $training['sub'] }}</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.trainings.index') }}" class="block text-xs text-center text-base-content/40 hover:text-base-content/70 transition-colors py-1">
                        Voir tout →
                    </a>
                </div>
                @endif

                {{-- PROCHAINS INTERCLUBS --}}
                @if(count($upcomingMatches) > 0)
                <div class="space-y-2 pt-1">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">Interclubs</p>
                    <div class="bg-base-100 rounded-xl border border-base-200 shadow-sm divide-y divide-base-200">
                        @foreach($upcomingMatches as $match)
                        <div class="px-3 py-2.5">
                            <p class="text-xs font-medium text-base-content leading-snug">{{ $match['label'] }}</p>
                            <p class="text-[10px] text-base-content/40 mt-0.5">{{ $match['sub'] }}</p>
                        </div>
                        @endforeach
                    </div>
                    <a href="{{ route('admin.interclubs.interclubs') }}" class="block text-xs text-center text-base-content/40 hover:text-base-content/70 transition-colors py-1">
                        Voir tout →
                    </a>
                </div>
                @endif

                {{-- TOURNOIS & RÉUNIONS --}}
                @if(count($upcomingInternalEvents) > 0)
                <div class="space-y-2 pt-1">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">Événements</p>
                    <div class="bg-base-100 rounded-xl border border-base-200 shadow-sm divide-y divide-base-200">
                        @foreach($upcomingInternalEvents as $event)
                        <div class="px-3 py-2.5">
                            <p class="text-xs font-medium text-base-content leading-snug">{{ $event['label'] }}</p>
                            <p class="text-[10px] text-base-content/40 mt-0.5">{{ $event['sub'] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

        </div>

    </div>
</x-app-layout>
