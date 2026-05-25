<x-app-layout>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="[['label' => 'Dashboard']]" />
    </x-slot:breadcrumbs>

    <div class="p-4 sm:p-6 space-y-5"
         x-data="{ feedFilter: 'all' }">

        {{-- HEADER --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-base-content">Bonjour, {{ Auth::user()->first_name }} 👋</h1>
                <p class="text-sm text-base-content/50 mt-0.5">{{ now()->translatedFormat('l j F Y') }}</p>
            </div>
        </div>

        {{-- COMPACT ALERTS — inline chips --}}
        <div class="flex flex-wrap gap-2 items-center">
            @if($members_unpaid > 0)
            <a href="#" class="inline-flex items-center gap-1.5 bg-warning/15 hover:bg-warning/25 text-warning-content border border-warning/30 rounded-full px-3 py-1 text-xs font-medium transition-colors">
                <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 text-warning" />
                {{ $members_unpaid }} cotisations impayées
            </a>
            @endif
            @if($interclubs_pending > 0)
            <a href="#" class="inline-flex items-center gap-1.5 bg-error/10 hover:bg-error/20 text-error border border-error/20 rounded-full px-3 py-1 text-xs font-medium transition-colors">
                <x-icon name="o-exclamation-circle" class="w-3.5 h-3.5" />
                {{ $interclubs_pending }} sélections manquantes
            </a>
            @endif
            @if($affiliations_pending > 0)
            <a href="#" class="inline-flex items-center gap-1.5 bg-info/10 hover:bg-info/20 text-info border border-info/20 rounded-full px-3 py-1 text-xs font-medium transition-colors">
                <x-icon name="o-document-text" class="w-3.5 h-3.5" />
                {{ $affiliations_pending }} affiliations en attente
            </a>
            @endif
            <span class="inline-flex items-center gap-1.5 bg-success/10 text-success border border-success/20 rounded-full px-3 py-1 text-xs font-medium">
                <x-icon name="o-check-circle" class="w-3.5 h-3.5" />
                Salles OK
            </span>
        </div>

        {{-- KPI STRIP --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-blue-300 hover:shadow-md transition-all group">
                <div class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 rounded-lg p-2 shrink-0">
                    <x-icon name="o-users" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $members_total }}</p>
                    <p class="text-xs text-base-content/50 truncate">Membres</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-violet-300 hover:shadow-md transition-all group">
                <div class="bg-violet-100 dark:bg-violet-900/30 text-violet-600 rounded-lg p-2 shrink-0">
                    <x-icon name="o-trophy" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $teams_count }}</p>
                    <p class="text-xs text-base-content/50 truncate">Équipes</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-rose-300 hover:shadow-md transition-all group">
                <div class="bg-rose-100 dark:bg-rose-900/30 text-rose-600 rounded-lg p-2 shrink-0">
                    <x-icon name="o-globe-alt" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $interclubs_pending }}</p>
                    <p class="text-xs text-base-content/50 truncate">Interclubs</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-amber-300 hover:shadow-md transition-all group">
                <div class="bg-amber-100 dark:bg-amber-900/30 text-amber-600 rounded-lg p-2 shrink-0">
                    <x-icon name="o-building-office-2" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $rooms_count }}</p>
                    <p class="text-xs text-base-content/50 truncate">Salles</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-yellow-300 hover:shadow-md transition-all group">
                <div class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 rounded-lg p-2 shrink-0">
                    <x-icon name="o-banknotes" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $payments_pending }}</p>
                    <p class="text-xs text-base-content/50 truncate">Paiements</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-emerald-300 hover:shadow-md transition-all group">
                <div class="bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 rounded-lg p-2 shrink-0">
                    <x-icon name="o-clock" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $trainings_count }}</p>
                    <p class="text-xs text-base-content/50 truncate">Entraîn.</p>
                </div>
            </a>

        </div>

        {{-- MAIN: Launcher by persona + Feed --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- LEFT: Persona accordions --}}
            <div class="lg:col-span-2 space-y-4">

                @php
                    $personaGroups = [
                        [
                            'key'    => 'secretaire',
                            'label'  => 'Secrétaire',
                            'count'  => 8,
                            'bg'     => 'bg-blue-50 dark:bg-blue-950/30',
                            'border' => 'border-blue-200 dark:border-blue-800',
                            'text'   => 'text-blue-700 dark:text-blue-400',
                            'dot'    => 'bg-blue-500',
                            'sep'    => 'border-blue-100 dark:border-blue-900',
                            'tiles'  => [
                                ['icon' => 'o-users',         'label' => 'Membres',      'sub' => $members_total . ' au total',          'color' => 'blue'],
                                ['icon' => 'o-user-plus',     'label' => 'Inscriptions', 'sub' => 'Nouvelles demandes',                  'color' => 'cyan'],
                                ['icon' => 'o-document-text', 'label' => 'Affiliations', 'sub' => $affiliations_pending . ' en attente', 'color' => 'indigo', 'badge' => $affiliations_pending],
                                ['icon' => 'o-newspaper',     'label' => 'Actualités',   'sub' => 'Articles & news',                     'color' => 'slate'],
                                ['icon' => 'o-envelope',      'label' => 'Contacts',     'sub' => 'Messages reçus',                      'color' => 'teal'],
                                ['icon' => 'o-calendar-days', 'label' => 'Réunions',     'sub' => 'Comptes rendus',                      'color' => 'purple'],
                                ['icon' => 'o-calendar',      'label' => 'Événements',   'sub' => $events_count . ' en cours',           'color' => 'pink'],
                                ['icon' => 'o-cog-6-tooth',   'label' => 'Paramètres',   'sub' => 'Club & saisons',                      'color' => 'gray'],
                            ],
                        ],
                        [
                            'key'    => 'tresorier',
                            'label'  => 'Trésorier',
                            'count'  => 6,
                            'bg'     => 'bg-amber-50 dark:bg-amber-950/30',
                            'border' => 'border-amber-200 dark:border-amber-800',
                            'text'   => 'text-amber-700 dark:text-amber-400',
                            'dot'    => 'bg-amber-500',
                            'sep'    => 'border-amber-100 dark:border-amber-900',
                            'tiles'  => [
                                ['icon' => 'o-banknotes',               'label' => 'Paiements',      'sub' => $payments_pending . ' en attente', 'color' => 'yellow', 'badge' => $payments_pending],
                                ['icon' => 'o-credit-card',             'label' => 'Transactions',   'sub' => 'Relevés bancaires',               'color' => 'teal'],
                                ['icon' => 'o-receipt-percent',         'label' => 'Cotisations',    'sub' => $members_unpaid . ' impayées',     'color' => 'amber',  'badge' => $members_unpaid],
                                ['icon' => 'o-clipboard-document-list', 'label' => 'Abonnements',   'sub' => 'Saisons en cours',                'color' => 'indigo'],
                                ['icon' => 'o-document-chart-bar',      'label' => 'Rapport',        'sub' => 'Aperçu financier',                'color' => 'slate'],
                                ['icon' => 'o-scale',                   'label' => 'Réconciliation', 'sub' => 'Soldes & vérifications',          'color' => 'gray'],
                            ],
                        ],
                        [
                            'key'    => 'capitaine',
                            'label'  => 'Capitaine / Sélectionneur',
                            'count'  => 6,
                            'bg'     => 'bg-rose-50 dark:bg-rose-950/30',
                            'border' => 'border-rose-200 dark:border-rose-800',
                            'text'   => 'text-rose-700 dark:text-rose-400',
                            'dot'    => 'bg-rose-500',
                            'sep'    => 'border-rose-100 dark:border-rose-900',
                            'tiles'  => [
                                ['icon' => 'o-trophy',                       'label' => 'Équipes',     'sub' => $teams_count . ' équipes',              'color' => 'violet'],
                                ['icon' => 'o-globe-alt',                    'label' => 'Interclubs',  'sub' => $interclubs_pending . ' en attente',    'color' => 'rose', 'badge' => $interclubs_pending],
                                ['icon' => 'o-clipboard-document-check',     'label' => 'Sélections',  'sub' => 'Compositions',                         'color' => 'orange'],
                                ['icon' => 'o-chart-bar',                    'label' => 'Résultats',   'sub' => 'Scores & classements',                 'color' => 'blue'],
                                ['icon' => 'o-calendar-days',                'label' => 'Planning',    'sub' => 'Calendrier matchs',                    'color' => 'emerald'],
                                ['icon' => 'o-user-group',                   'label' => 'Joueurs',     'sub' => $members_competitors . ' compétiteurs', 'color' => 'purple'],
                            ],
                        ],
                        [
                            'key'    => 'comite',
                            'label'  => 'Comité',
                            'count'  => 8,
                            'bg'     => 'bg-violet-50 dark:bg-violet-950/30',
                            'border' => 'border-violet-200 dark:border-violet-800',
                            'text'   => 'text-violet-700 dark:text-violet-400',
                            'dot'    => 'bg-violet-500',
                            'sep'    => 'border-violet-100 dark:border-violet-900',
                            'tiles'  => [
                                ['icon' => 'o-users',             'label' => 'Membres',        'sub' => $members_total . ' inscrits',    'color' => 'blue'],
                                ['icon' => 'o-building-office-2', 'label' => 'Salles',         'sub' => $rooms_count . ' installations', 'color' => 'amber'],
                                ['icon' => 'o-clock',             'label' => 'Entraînements',  'sub' => $trainings_count . ' séances',   'color' => 'emerald'],
                                ['icon' => 'o-calendar-days',     'label' => 'Saisons',        'sub' => 'Gestion des périodes',          'color' => 'indigo'],
                                ['icon' => 'o-newspaper',         'label' => 'Actualités',     'sub' => 'Site public',                   'color' => 'slate'],
                                ['icon' => 'o-megaphone',         'label' => 'Événements',     'sub' => $events_count . ' planifié(s)', 'color' => 'pink'],
                                ['icon' => 'o-calendar',          'label' => 'Réunions',       'sub' => 'Comptes rendus',                'color' => 'purple'],
                                ['icon' => 'o-cog-6-tooth',       'label' => 'Configuration',  'sub' => 'Paramètres club',               'color' => 'gray'],
                            ],
                        ],
                    ];
                @endphp

                @foreach($personaGroups as $group)
                <section x-data="{ open: true }">

                    {{-- Accordion header — same pattern as teams/index --}}
                    <button type="button"
                        class="mb-3 flex w-full items-center gap-3 text-left"
                        @click="open = !open">
                        <span class="inline-flex items-center gap-2 rounded-full {{ $group['bg'] }} {{ $group['border'] }} border px-4 py-1.5">
                            <span class="h-2 w-2 rounded-full {{ $group['dot'] }}"></span>
                            <span class="text-sm font-bold {{ $group['text'] }} uppercase tracking-wide">{{ $group['label'] }}</span>
                            <span class="text-xs {{ $group['text'] }} opacity-60">{{ $group['count'] }} accès</span>
                        </span>
                        <div class="flex-1 border-t {{ $group['sep'] }}"></div>
                        <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                    </button>

                    <div x-show="open" x-collapse>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                            @foreach($group['tiles'] as $tile)
                                @include('clubAdmin._dashboard_tile', $tile)
                            @endforeach
                        </div>
                    </div>

                </section>
                @endforeach

            </div>

            {{-- RIGHT: Activity feed --}}
            <div class="lg:col-span-1 space-y-3">

                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">Activité récente</p>
                </div>

                {{-- Feed filter --}}
                <div class="flex gap-1 flex-wrap">
                    @foreach([
                        ['key' => 'all',       'label' => 'Tout'],
                        ['key' => 'member',    'label' => 'Membres'],
                        ['key' => 'payment',   'label' => 'Paiements'],
                        ['key' => 'match',     'label' => 'Matchs'],
                        ['key' => 'contact',   'label' => 'Contacts'],
                        ['key' => 'news',      'label' => 'News'],
                        ['key' => 'meeting',   'label' => 'Réunions'],
                    ] as $f)
                    <button
                        @click="feedFilter = '{{ $f['key'] }}'"
                        :class="feedFilter === '{{ $f['key'] }}' ? 'bg-neutral text-neutral-content' : 'bg-base-200 text-base-content/60 hover:bg-base-300'"
                        class="btn btn-xs rounded-full transition-all">
                        {{ $f['label'] }}
                    </button>
                    @endforeach
                </div>

                <div class="bg-base-100 rounded-xl border border-base-200 shadow-sm divide-y divide-base-200">

                    @foreach($recent_activity as $item)
                    @php
                        $cfg = [
                            'member'    => ['icon' => 'o-user',          'bg' => 'bg-blue-100 dark:bg-blue-900/30',    'text' => 'text-blue-600'],
                            'payment'   => ['icon' => 'o-banknotes',     'bg' => 'bg-yellow-100 dark:bg-yellow-900/30','text' => 'text-yellow-600'],
                            'match'     => ['icon' => 'o-globe-alt',     'bg' => 'bg-rose-100 dark:bg-rose-900/30',    'text' => 'text-rose-600'],
                            'selection' => ['icon' => 'o-clipboard-document-check', 'bg' => 'bg-orange-100 dark:bg-orange-900/30', 'text' => 'text-orange-600'],
                            'contact'   => ['icon' => 'o-envelope',      'bg' => 'bg-teal-100 dark:bg-teal-900/30',   'text' => 'text-teal-600'],
                            'news'      => ['icon' => 'o-newspaper',     'bg' => 'bg-slate-100 dark:bg-slate-900/30', 'text' => 'text-slate-600'],
                            'meeting'   => ['icon' => 'o-calendar-days', 'bg' => 'bg-purple-100 dark:bg-purple-900/30','text' => 'text-purple-600'],
                        ][$item['type']] ?? ['icon' => 'o-bell', 'bg' => 'bg-base-200', 'text' => 'text-base-content'];
                    @endphp
                    <div x-show="feedFilter === 'all' || feedFilter === '{{ $item['type'] }}'"
                         x-transition.opacity
                         class="flex items-start gap-3 px-3 py-2.5 hover:bg-base-200/50 transition-colors">
                        <div class="{{ $cfg['bg'] }} {{ $cfg['text'] }} rounded-full p-1.5 shrink-0 mt-0.5">
                            <x-icon name="{{ $cfg['icon'] }}" class="w-3.5 h-3.5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-base-content leading-snug">{{ $item['label'] }}</p>
                        </div>
                        <span class="text-xs text-base-content/30 shrink-0 tabular-nums">{{ $item['time'] }}</span>
                    </div>
                    @endforeach

                </div>

            </div>

        </div>

    </div>
</x-app-layout>
