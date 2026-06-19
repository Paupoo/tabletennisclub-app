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
                <x-icon name="o-exclamation-triangle" class="w-3.5 h-3.5 text-warning-content" />
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

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-md transition-all group">
                <div class="bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-lg p-2 shrink-0">
                    <x-icon name="o-users" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $members_total }}</p>
                    <p class="text-xs text-base-content/50 truncate">Membres</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-violet-300 dark:hover:border-violet-700 hover:shadow-md transition-all group">
                <div class="bg-violet-100 dark:bg-violet-900/30 text-violet-600 dark:text-violet-400 rounded-lg p-2 shrink-0">
                    <x-icon name="o-trophy" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $teams_count }}</p>
                    <p class="text-xs text-base-content/50 truncate">{{ __('Teams') }}</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-rose-300 dark:hover:border-rose-700 hover:shadow-md transition-all group">
                <div class="bg-rose-100 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 rounded-lg p-2 shrink-0">
                    <x-icon name="o-globe-alt" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $interclubs_pending }}</p>
                    <p class="text-xs text-base-content/50 truncate">Interclubs</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-amber-300 dark:hover:border-amber-700 hover:shadow-md transition-all group">
                <div class="bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-lg p-2 shrink-0">
                    <x-icon name="o-building-office-2" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $rooms_count }}</p>
                    <p class="text-xs text-base-content/50 truncate">Salles</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-yellow-300 dark:hover:border-yellow-700 hover:shadow-md transition-all group">
                <div class="bg-yellow-100 dark:bg-yellow-900/30 text-yellow-600 dark:text-yellow-400 rounded-lg p-2 shrink-0">
                    <x-icon name="o-banknotes" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $payments_pending }}</p>
                    <p class="text-xs text-base-content/50 truncate">Paiements</p>
                </div>
            </a>

            <a href="#" class="bg-base-100 rounded-xl border border-base-200 shadow-sm px-4 py-3 flex items-center gap-3 hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md transition-all group">
                <div class="bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 rounded-lg p-2 shrink-0">
                    <x-icon name="o-clock" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <p class="text-lg font-black text-base-content leading-none">{{ $trainings_count }}</p>
                    <p class="text-xs text-base-content/50 truncate">{{ __('Training') }}</p>
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
                            'label'  => __('Secretary'),
                            'count'  => 8,
                            'color'  => 'blue',
                            'tiles'  => [
                                ['icon' => 'o-users',         'label' => 'Membres',      'sub' => $members_total . ' au total',          'color' => 'blue'],
                                ['icon' => 'o-user-plus',     'label' => 'Inscriptions', 'sub' => 'Nouvelles demandes',                  'color' => 'cyan'],
                                ['icon' => 'o-document-text', 'label' => 'Affiliations', 'sub' => $affiliations_pending . ' en attente', 'color' => 'indigo', 'badge' => $affiliations_pending],
                                ['icon' => 'o-newspaper',     'label' => __('News'),   'sub' => 'Articles & news',                     'color' => 'slate'],
                                ['icon' => 'o-envelope',      'label' => 'Contacts',     'sub' => __('Received messages'),                      'color' => 'teal'],
                                ['icon' => 'o-calendar-days', 'label' => __('Meetings'),     'sub' => 'Comptes rendus',                      'color' => 'purple'],
                                ['icon' => 'o-calendar',      'label' => __('Events'),   'sub' => $events_count . ' en cours',           'color' => 'pink'],
                                ['icon' => 'o-cog-6-tooth',   'label' => __('Settings'),   'sub' => 'Club & saisons',                      'color' => 'gray'],
                            ],
                        ],
                        [
                            'label'  => __('Treasurer'),
                            'count'  => 6,
                            'color'  => 'amber',
                            'tiles'  => [
                                ['icon' => 'o-banknotes',               'label' => 'Paiements',      'sub' => $payments_pending . ' en attente', 'color' => 'yellow', 'badge' => $payments_pending],
                                ['icon' => 'o-credit-card',             'label' => 'Transactions',   'sub' => __('Bank statements'),               'color' => 'teal'],
                                ['icon' => 'o-receipt-percent',         'label' => 'Cotisations',    'sub' => $members_unpaid . ' impayées',     'color' => 'amber',  'badge' => $members_unpaid],
                                ['icon' => 'o-clipboard-document-list', 'label' => 'Abonnements',   'sub' => 'Saisons en cours',                'color' => 'indigo'],
                                ['icon' => 'o-document-chart-bar',      'label' => 'Rapport',        'sub' => __('Financial overview'),                'color' => 'slate'],
                                ['icon' => 'o-scale',                   'label' => __('Reconciliation'), 'sub' => __('Balances & verifications'),          'color' => 'gray'],
                            ],
                        ],
                        [
                            'label'  => __('Captain / Selector'),
                            'count'  => 6,
                            'color'  => 'rose',
                            'tiles'  => [
                                ['icon' => 'o-trophy',                       'label' => __('Teams'),     'sub' => $teams_count . ' équipes',              'color' => 'violet'],
                                ['icon' => 'o-globe-alt',                    'label' => 'Interclubs',  'sub' => $interclubs_pending . ' en attente',    'color' => 'rose', 'badge' => $interclubs_pending],
                                ['icon' => 'o-clipboard-document-check',     'label' => __('Selections'),  'sub' => 'Compositions',                         'color' => 'orange'],
                                ['icon' => 'o-chart-bar',                    'label' => __('Results'),   'sub' => 'Scores & classements',                 'color' => 'blue'],
                                ['icon' => 'o-calendar-days',                'label' => 'Planning',    'sub' => 'Calendrier matchs',                    'color' => 'emerald'],
                                ['icon' => 'o-user-group',                   'label' => 'Joueurs',     'sub' => $members_competitors . ' compétiteurs', 'color' => 'purple'],
                            ],
                        ],
                        [
                            'label'  => __('Committee'),
                            'count'  => 8,
                            'color'  => 'violet',
                            'tiles'  => [
                                ['icon' => 'o-users',             'label' => 'Membres',        'sub' => $members_total . ' inscrits',    'color' => 'blue'],
                                ['icon' => 'o-building-office-2', 'label' => 'Salles',         'sub' => $rooms_count . ' installations', 'color' => 'amber'],
                                ['icon' => 'o-clock',             'label' => __('Training sessions'),  'sub' => $trainings_count . ' séances',   'color' => 'emerald'],
                                ['icon' => 'o-calendar-days',     'label' => 'Saisons',        'sub' => __('Period management'),          'color' => 'indigo'],
                                ['icon' => 'o-newspaper',         'label' => __('News'),     'sub' => 'Site public',                   'color' => 'slate'],
                                ['icon' => 'o-megaphone',         'label' => __('Events'),     'sub' => $events_count . ' planifié(s)', 'color' => 'pink'],
                                ['icon' => 'o-calendar',          'label' => __('Meetings'),       'sub' => 'Comptes rendus',                'color' => 'purple'],
                                ['icon' => 'o-cog-6-tooth',       'label' => 'Configuration',  'sub' => __('Club settings'),               'color' => 'gray'],
                            ],
                        ],
                    ];
                @endphp

                @foreach($personaGroups as $group)
                <x-section-accordion
                    :label="$group['label']"
                    :count="$group['count'] . ' accès'"
                    :color="$group['color']">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 pb-2">
                        @foreach($group['tiles'] as $tile)
                            @include('clubAdmin._dashboard_tile', $tile)
                        @endforeach
                    </div>
                </x-section-accordion>
                @endforeach

            </div>

            {{-- RIGHT: Activity feed --}}
            <div class="lg:col-span-1 space-y-3">

                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-base-content/40 uppercase tracking-wider">{{ __('Recent activity') }}</p>
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
                        ['key' => 'meeting',   'label' => __('Meetings')],
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
                            'member'    => ['icon' => 'o-user',          'bg' => 'bg-blue-100 dark:bg-blue-900/30',    'text' => 'text-blue-600 dark:text-blue-400'],
                            'payment'   => ['icon' => 'o-banknotes',     'bg' => 'bg-yellow-100 dark:bg-yellow-900/30','text' => 'text-yellow-600 dark:text-yellow-400'],
                            'match'     => ['icon' => 'o-globe-alt',     'bg' => 'bg-rose-100 dark:bg-rose-900/30',    'text' => 'text-rose-600 dark:text-rose-400'],
                            'selection' => ['icon' => 'o-clipboard-document-check', 'bg' => 'bg-orange-100 dark:bg-orange-900/30', 'text' => 'text-orange-600 dark:text-orange-400'],
                            'contact'   => ['icon' => 'o-envelope',      'bg' => 'bg-teal-100 dark:bg-teal-900/30',   'text' => 'text-teal-600 dark:text-teal-400'],
                            'news'      => ['icon' => 'o-newspaper',     'bg' => 'bg-slate-100 dark:bg-slate-800/40', 'text' => 'text-slate-600 dark:text-slate-400'],
                            'meeting'   => ['icon' => 'o-calendar-days', 'bg' => 'bg-purple-100 dark:bg-purple-900/30','text' => 'text-purple-600 dark:text-purple-400'],
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
