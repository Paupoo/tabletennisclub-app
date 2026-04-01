<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>
<div>

    <x-header separator subtitle="{{ __('Seniors - Division 3B') }}" title="Ottignies B">
        <x-slot:actions>
            {{-- BOUTON CHAT (ouvre le thread de la semaine courante) --}}
            <x-button class="btn-circle btn-ghost relative" icon="o-chat-bubble-left-right"
                wire:click="openChatDrawer(13)">
                <span class="bg-error ring-base-100 absolute right-2 top-2 h-2 w-2 rounded-full ring-2"></span>
            </x-button>
        </x-slot:actions>
    </x-header>

    {{-- MOT DU CAPITAINE --}}
    <x-admin.shared.info-bar :title="__('Captain\'s Note (WK13)')" :description="__('N\'oubliez pas le covoiturage pour Perwez, départ 18h45 du club ! Marc.')">
        <x-slot:action>
            <x-button class="btn-sm btn-ghost" icon="o-chat-bubble-left" label="{{ __('Reply') }}"
                wire:click="openChatDrawer(13)" />
        </x-slot:action>
    </x-admin.shared.info-bar>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        {{-- COLONNE GAUCHE : CLASSEMENT & ROSTER --}}
        <div class="space-y-6">

            {{-- Sélecteur d'équipe --}}
            <x-admin.shared.side-card shadow title="{{ __('Select a team') }}">
                <x-choices :options="$teams" placeholder="{{ __('Select a team') }}" single wire:model="selectedTeam" />
            </x-admin.shared.side-card>

            {{-- Classement --}}
            <x-admin.shared.side-card icon="o-list-bullet" separator shadow title="{{ __('Division Standing') }}">
                <div
                    class="bg-primary/10 border-primary/10 mb-4 flex items-center justify-between rounded-xl border p-4">
                    <div>
                        <div class="text-[10px] font-black uppercase opacity-50">{{ __('Rank') }}</div>
                        <div class="text-primary text-3xl font-black">4<span class="text-sm opacity-50">/12</span></div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-black uppercase opacity-50">{{ __('Points') }}</div>
                        <div class="text-xl font-bold">28</div>
                    </div>
                </div>

                <div class="space-y-1">
                    @php
                        $standing = [
                            ['pos' => 1, 'team' => 'Wavre A', 'pts' => 36, 'me' => false],
                            ['pos' => 2, 'team' => 'Perwez B', 'pts' => 32, 'me' => false],
                            ['pos' => 3, 'team' => 'Champ d\'en Haut', 'pts' => 30, 'me' => false],
                            ['pos' => 4, 'team' => 'Ottignies B', 'pts' => 28, 'me' => true],
                            ['pos' => 5, 'team' => 'Logis J', 'pts' => 24, 'me' => false],
                        ];
                    @endphp
                    @foreach ($standing as $s)
                        <div @class([
                            'flex items-center justify-between p-2 text-[11px] rounded-lg',
                            'bg-primary/10 font-black border border-primary/20' => $s['me'],
                        ])>
                            <span class="w-4 opacity-50">{{ $s['pos'] }}</span>
                            <span class="mx-2 flex-1 truncate">{{ $s['team'] }}</span>
                            <span class="font-mono">{{ $s['pts'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-admin.shared.side-card>

            {{-- Roster --}}
            <x-admin.shared.side-card icon="o-users" separator shadow title="{{ __('Roster') }}">
                <div class="space-y-1">
                    @php
                        $players = [
                            [
                                'name' => 'Marc D.',
                                'rank' => 'B4',
                                'role' => 'Captain',
                                'img' => 'https://i.pravatar.cc/150?u=1',
                            ],
                            [
                                'name' => 'Aurelien V.',
                                'rank' => 'B6',
                                'role' => 'Player',
                                'img' => 'https://i.pravatar.cc/150?u=2',
                            ],
                            [
                                'name' => 'Jean-Paul H.',
                                'rank' => 'C0',
                                'role' => 'Player',
                                'img' => 'https://i.pravatar.cc/150?u=3',
                            ],
                            [
                                'name' => 'Luc L.',
                                'rank' => 'C2',
                                'role' => 'Reserve',
                                'img' => 'https://i.pravatar.cc/150?u=4',
                            ],
                        ];
                    @endphp
                    @foreach ($players as $player)
                        <x-list-item :item="[]" class="!p-1" no-hover no-separator>
                            <x-slot:avatar>
                                <x-avatar :image="$player['img']" class="!w-7" />
                            </x-slot:avatar>
                            <x-slot:value>
                                <span class="text-xs font-bold">{{ $player['name'] }}</span>
                            </x-slot:value>
                            <x-slot:sub-value class="text-[9px]">{{ $player['rank'] }}</x-slot:sub-value>
                        </x-list-item>
                    @endforeach
                </div>
            </x-admin.shared.side-card>
        </div>

        {{-- COLONNE CENTRALE --}}
        <div class="space-y-6 lg:col-span-3">

            {{-- PLANNING --}}
            <x-card icon="o-calendar" separator shadow title="{{ __('Upcoming Schedule') }}">
                <x-slot:menu>
                    {{-- <div
                        class="hidden items-center gap-3 px-2 text-[9px] font-black uppercase tracking-tighter opacity-40 md:flex">
                        <span class="flex items-center gap-1">
                            <x-icon class="text-success h-1.5 w-1.5 rounded-full" name="o-check" />
                            <div class="bg-success h-1.5 w-1.5 rounded-full"></div> {{ __('Available') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="bg-info h-1.5 w-1.5 rounded-full"></div> {{ __('Uncertain') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <div class="bg-error h-1.5 w-1.5 rounded-full"></div> {{ __('Absent') }}
                        </span>
                    </div> --}}
                </x-slot:menu>

                <div class="space-y-1">
                    @php
                        $upcoming = [
                            [
                                'week' => 13,
                                'day' => 'Fri',
                                'startDateTime' => '2026-02-05 20:00',
                                'opp' => 'Perwez A',
                                'loc' => 'Away',
                                'address' => 'Rue de la Station 12, 1360 Perwez',
                                'has_thread' => true,
                            ],
                            [
                                'week' => 14,
                                'day' => 'Sat',
                                'startDateTime' => '2026-02-13 19:30',
                                'opp' => 'Wavre C',
                                'loc' => 'Home',
                                'address' => 'Av. des Combattants 19, 1340 LLN',
                                'has_thread' => false,
                            ],
                            [
                                'week' => 15,
                                'day' => 'Fri',
                                'startDateTime' => '2026-02-20 20:00',
                                'opp' => 'Logis J',
                                'loc' => 'Away',
                                'address' => 'Chaussée de Wavre 2000, 1160 Auderghem',
                                'has_thread' => false,
                            ],
                            [
                                'week' => 16,
                                'day' => 'Sat',
                                'startDateTime' => '2026-02-27 19:00',
                                'opp' => 'Auderghem E',
                                'loc' => 'Home',
                                'address' => 'Av. des Combattants 19, 1340 LLN',
                                'has_thread' => false,
                            ],
                            [
                                'week' => 17,
                                'day' => 'Fri',
                                'startDateTime' => '2026-03-06 20:00',
                                'opp' => 'Champ d\'en Haut',
                                'loc' => 'Away',
                                'address' => 'Rue du Village 5, 5020 Namur',
                                'has_thread' => false,
                            ],
                        ];
                    @endphp

                    @foreach ($upcoming as $match)
                        <x-admin.shared.compact-event-preview :address="$match['address']" :location="$match['loc']" :name="__('Match vs :opponent', ['opponent' => $match['opp']])"
                            :opponent="$match['opp']" :startDateTime="$match['startDateTime']" :type="'interclub'" :week="$match['week']">
                            <x-slot:actions>
                                {{-- Bouton thread de la semaine --}}
                                <div class="tooltip tooltip-left relative"
                                    data-tip="{{ __('Week :week thread', ['week' => $match['week']]) }}">
                                    <x-button
                                        class="btn-ghost btn-xs hover:bg-base-300 h-8 w-8 rounded-md transition-all"
                                        icon="o-chat-bubble-oval-left-ellipsis"
                                        wire:click="openChatDrawer({{ $match['week'] }})" />
                                    @if ($match['has_thread'])
                                        <span
                                            class="bg-primary pointer-events-none absolute right-1 top-1 h-1.5 w-1.5 rounded-full"></span>
                                    @endif
                                </div>

                                {{-- Disponibilité --}}
                                <div class="bg-base-200/50 border-base-300/50 flex gap-1 rounded-lg border p-1">
                                    <x-button
                                        class="btn-ghost btn-xs hover:bg-success h-8 w-8 rounded-md transition-all hover:text-white"
                                        icon="o-check" tooltip-left="{{ __('Available') }}" />
                                    <x-button
                                        class="btn-ghost btn-xs hover:bg-info h-8 w-8 rounded-md transition-all hover:text-white"
                                        icon="o-question-mark-circle" tooltip-left="{{ __('Uncertain') }}" />
                                    <x-button
                                        class="btn-ghost btn-xs hover:bg-error h-8 w-8 rounded-md transition-all hover:text-white"
                                        icon="o-x-mark" tooltip-left="{{ __('Absent') }}" />
                                </div>
                            </x-slot:actions>
                        </x-admin.shared.compact-event-preview>
                    @endforeach

                    <div class="flex justify-center pt-4">
                        <x-button class="btn-ghost btn-sm text-xs opacity-40 hover:opacity-100"
                            icon-right="o-plus-small" label="{{ __('See more matches') }}" />
                    </div>
                </div>
            </x-card>

            {{-- RÉSULTATS --}}
            <x-card icon="o-bolt" separator shadow title="{{ __('Latest Results') }}">
                @php
                    $results = [
                        [
                            'week' => 12,
                            'startDateTime' => '28/01/2026',
                            'my_team' => 'Ottignies B',
                            'category' => 'Seniors',
                            'opponent' => 'Mont-Saint-Guibert C',
                            'score' => '12 - 4',
                            'win' => true,
                            'matches' => [
                                ['opp_name' => 'Dupont A.', 'opp_rank' => 'C0', 'sets' => '3 - 1', 'win' => true],
                                ['opp_name' => 'Durand L.', 'opp_rank' => 'C2', 'sets' => '3 - 0', 'win' => true],
                                ['opp_name' => 'Lefebvre G.', 'opp_rank' => 'B6', 'sets' => '2 - 3', 'win' => false],
                                ['opp_name' => 'Moreau P.', 'opp_rank' => 'C0', 'sets' => '3 - 1', 'win' => true],
                            ],
                        ],
                        [
                            'week' => 11,
                            'startDateTime' => '15/01/2026',
                            'my_team' => 'Ottignies B',
                            'category' => 'Seniors',
                            'opponent' => 'Auderghem J',
                            'score' => '7 - 9',
                            'win' => false,
                            'matches' => [
                                ['opp_name' => 'Dewit F.', 'opp_rank' => 'B2', 'sets' => '0 - 3', 'win' => false],
                                ['opp_name' => 'Anciaux T.', 'opp_rank' => 'B6', 'sets' => '1 - 3', 'win' => false],
                            ],
                        ],
                    ];
                @endphp

                <div class="space-y-4">
                    @foreach ($results as $res)
                        <x-collapse class="border-base-200 overflow-hidden rounded-xl border shadow-sm" no-icon>
                            <x-slot:heading>
                                <div class="flex w-full items-center justify-between py-1 pr-4">
                                    <div class="flex items-center gap-4">
                                        <div @class([
                                            'w-1.5 h-10 rounded-full',
                                            'bg-success' => $res['win'],
                                            'bg-error' => !$res['win'],
                                        ])></div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-xs font-black uppercase leading-none tracking-widest opacity-40">WK{{ $res['week'] }}</span>
                                                <span class="text-sm font-bold">{{ $res['my_team'] }} <span
                                                        class="text-xs font-normal italic opacity-30">vs</span>
                                                    {{ $res['opponent'] }}</span>
                                            </div>
                                            <div class="mt-0.5 text-[10px] uppercase opacity-50">{{ $res['category'] }}
                                                • {{ $res['startDateTime'] }}</div>
                                        </div>
                                    </div>

                                    @php $scores = explode('-', $res['score']); @endphp
                                    <div class="flex scale-90 items-center gap-0.5">
                                        <div
                                            class="bg-neutral text-neutral-content min-w-[24px] rounded px-2 py-0.5 text-center font-mono text-sm font-black">
                                            {{ trim($scores[0]) }}
                                        </div>
                                        <div class="text-neutral/30 font-bold">-</div>
                                        <div
                                            class="bg-neutral text-neutral-content min-w-[24px] rounded px-2 py-0.5 text-center font-mono text-sm font-black">
                                            {{ trim($scores[1]) }}
                                        </div>
                                        <x-icon class="ml-4 h-4 w-4 opacity-30" name="o-chevron-down" />
                                    </div>
                                </div>
                            </x-slot:heading>

                            <x-slot:content>
                                <div class="bg-base-100 border-base-200 border-t p-4">
                                    <x-tabs selected="me-tab-{{ $res['week'] }}">
                                        <x-tab icon="o-user" label="{{ __('My Matches') }}"
                                            name="me-tab-{{ $res['week'] }}">
                                            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                                                @foreach ($res['matches'] as $match)
                                                    <div @class([
                                                        'flex items-center justify-between p-3 rounded-lg border bg-base-100 shadow-sm',
                                                        'border-success/30' => $match['win'],
                                                        'border-error/30' => !$match['win'],
                                                    ])>
                                                        <div class="flex items-center gap-3 text-xs">
                                                            <div @class([
                                                                'w-2 h-2 rounded-full',
                                                                'bg-success shadow-[0_0_5px_rgba(34,197,94,0.5)]' => $match['win'],
                                                                'bg-error shadow-[0_0_5px_rgba(239,68,68,0.5)]' => !$match['win'],
                                                            ])></div>
                                                            <div>
                                                                <div class="font-bold">{{ $match['opp_name'] }}</div>
                                                                <div
                                                                    class="text-[9px] uppercase tracking-tighter opacity-50">
                                                                    {{ $match['opp_rank'] }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <span @class([
                                                            'font-mono font-black text-sm px-2 rounded',
                                                            'text-success' => $match['win'],
                                                            'text-error' => !$match['win'],
                                                        ])>{{ $match['sets'] }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </x-tab>

                                        <x-tab icon="o-users" label="{{ __('Teammates') }}"
                                            name="team-tab-{{ $res['week'] }}">
                                            <div class="mt-4 space-y-4">
                                                @php
                                                    $teammates = [
                                                        [
                                                            'name' => 'Marc D.',
                                                            'results' => [
                                                                ['s' => '3-0', 'w' => true],
                                                                ['s' => '3-2', 'w' => true],
                                                                ['s' => '1-3', 'w' => false],
                                                                ['s' => '3-0', 'w' => true],
                                                            ],
                                                        ],
                                                        [
                                                            'name' => 'Jean-Paul H.',
                                                            'results' => [
                                                                ['s' => '0-3', 'w' => false],
                                                                ['s' => '1-3', 'w' => false],
                                                                ['s' => '0-3', 'w' => false],
                                                                ['s' => '2-3', 'w' => false],
                                                            ],
                                                        ],
                                                        [
                                                            'name' => 'Luc L.',
                                                            'results' => [
                                                                ['s' => '3-1', 'w' => true],
                                                                ['s' => '3-1', 'w' => true],
                                                                ['s' => '3-0', 'w' => true],
                                                                ['s' => '3-2', 'w' => true],
                                                            ],
                                                        ],
                                                    ];
                                                @endphp
                                                @foreach ($teammates as $mate)
                                                    <div
                                                        class="border-base-200 flex items-center justify-between border-b py-2 last:border-0">
                                                        <div class="w-1/3 text-xs font-bold">{{ $mate['name'] }}</div>
                                                        <div class="flex w-2/3 justify-end gap-1.5">
                                                            @foreach ($mate['results'] as $m_res)
                                                                <div class="tooltip" data-tip="{{ $m_res['s'] }}">
                                                                    <div @class([
                                                                        'w-7 h-7 rounded flex items-center justify-center text-[9px] font-black border',
                                                                        'bg-success/10 text-success border-success/20' => $m_res['w'],
                                                                        'bg-error/10 text-error border-error/20' => !$m_res['w'],
                                                                    ])>
                                                                        {{ $m_res['w'] ? 'W' : 'L' }}
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </x-tab>
                                    </x-tabs>
                                </div>
                            </x-slot:content>
                        </x-collapse>
                    @endforeach
                </div>
            </x-card>

        </div>
    </div>

    {{-- =====================================================
         CHAT DRAWER — threads par semaine
         ===================================================== --}}
    <x-drawer class="w-11/12 lg:w-1/3" right separator subtitle="{{ __('Organisation and Logistics') }}"
        title="{{ __('Team Chat') }}" wire:model="chatDrawer" with-close-button>

        <div class="flex h-[calc(100vh-200px)] flex-col">

            {{-- ── Sélecteur de thread (semaine) ────────────────── --}}
            <div class="mb-4">
                <div class="mb-2 text-[10px] font-black uppercase tracking-wider opacity-40">
                    {{ __('Week thread') }}
                </div>

                {{-- Tabs de semaines --}}
                <div class="scrollbar-thin flex gap-1 overflow-x-auto pb-1">
                    @php
                        $threads = [
                            ['week' => 13, 'opp' => 'Perwez A', 'unread' => 2],
                            ['week' => 14, 'opp' => 'Wavre C', 'unread' => 0],
                            ['week' => 15, 'opp' => 'Logis J', 'unread' => 0],
                            ['week' => 16, 'opp' => 'Auderghem E', 'unread' => 0],
                            ['week' => 17, 'opp' => 'Champ d\'en Haut', 'unread' => 0],
                        ];
                    @endphp

                    @foreach ($threads as $thread)
                        <button @class([
                            'relative flex flex-col items-center px-3 py-2 rounded-xl border text-center transition-all shrink-0',
                            'bg-primary text-primary-content border-primary shadow-md shadow-primary/20' =>
                                $activeWeek === $thread['week'],
                            'bg-base-200/50 border-base-300 hover:bg-base-200 text-base-content/70' =>
                                $activeWeek !== $thread['week'],
                        ]) wire:click="selectWeek({{ $thread['week'] }})">
                            <span
                                class="text-[10px] font-black uppercase leading-none tracking-widest">WK{{ $thread['week'] }}</span>
                            <span
                                class="mt-0.5 max-w-[60px] truncate text-[9px] opacity-70">{{ $thread['opp'] }}</span>

                            @if ($thread['unread'] > 0)
                                <span
                                    class="bg-error absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full text-[9px] font-black text-white">
                                    {{ $thread['unread'] }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Résumé du thread actif --}}
                <div class="bg-base-200/50 border-base-300/50 mt-3 flex items-center gap-3 rounded-xl border p-2.5">
                    <x-icon class="h-4 w-4 shrink-0 opacity-40" name="o-calendar-days" />
                    <div class="text-[10px] leading-tight">
                        <span
                            class="font-black uppercase tracking-wide opacity-60">{{ __('Thread WK:week', ['week' => $activeWeek]) }}</span>
                        <span class="mx-1 opacity-40">·</span>
                        {{-- Nom de l'adversaire de la semaine active --}}
                        @php $activeThread = collect($threads)->firstWhere('week', $activeWeek); @endphp
                        <span class="font-semibold">{{ $activeThread['opp'] ?? '' }}</span>
                    </div>
                </div>
            </div>

            {{-- ── Messages du thread actif ──────────────────────── --}}
            <div class="flex-1 space-y-6 overflow-y-auto pr-2">
                @php
                    $allMessages = [
                        13 => [
                            [
                                'user' => 'Marc D.',
                                'text' => 'Hello l\'équipe ! Des dispo pour le covoiturage vendredi ?',
                                'time' => '10:05',
                                'me' => false,
                                'color' => 'bg-base-200',
                            ],
                            [
                                'user' => 'Aurelien V.',
                                'text' => 'Je peux prendre ma voiture, j\'ai 3 places libres.',
                                'time' => '10:12',
                                'me' => false,
                                'color' => 'bg-base-200',
                            ],
                            [
                                'user' => 'Moi',
                                'text' => 'Top Aurelien, je monte avec toi ! On se dit 18h40 au club ?',
                                'time' => '10:15',
                                'me' => true,
                                'color' => 'bg-primary text-primary-content',
                            ],
                            [
                                'user' => 'Aurelien V.',
                                'text' => 'Parfait pour 18h40. 👌',
                                'time' => '10:16',
                                'me' => false,
                                'color' => 'bg-base-200',
                            ],
                        ],
                        14 => [],
                        15 => [],
                        16 => [],
                        17 => [],
                    ];
                    $messages = $allMessages[$activeWeek] ?? [];
                @endphp

                @if (count($messages) === 0)
                    <div class="flex h-full flex-col items-center justify-center gap-2 py-12 opacity-30">
                        <x-icon class="h-10 w-10" name="o-chat-bubble-oval-left-ellipsis" />
                        <p class="text-xs font-bold uppercase tracking-wider">{{ __('No messages yet') }}</p>
                        <p class="text-[10px]">{{ __('Be the first to write in this thread!') }}</p>
                    </div>
                @else
                    @foreach ($messages as $msg)
                        <div @class(['flex flex-col', 'items-end' => $msg['me']])>
                            <div class="mb-1 flex items-center gap-2">
                                @if (!$msg['me'])
                                    <span class="text-[10px] font-black opacity-40">{{ $msg['user'] }}</span>
                                @endif
                                <span class="text-[9px] opacity-30">{{ $msg['time'] }}</span>
                            </div>
                            <div @class([
                                'max-w-[85%] p-3 rounded-2xl text-xs font-medium shadow-sm',
                                $msg['color'],
                                'rounded-tr-none' => $msg['me'],
                                'rounded-tl-none' => !$msg['me'],
                            ])>
                                {{ $msg['text'] }}
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            {{-- ── Zone d'envoi ──────────────────────────────────── --}}
            <div class="border-base-200 mt-auto border-t pt-6">
                <div class="flex items-end gap-2">
                    <x-textarea
                        class="input-sm bg-base-200/50 focus:ring-primary h-auto min-h-[40px] rounded-xl border-none"
                        placeholder="{{ __('Your message for WK:week…', ['week' => $activeWeek]) }}"
                        rows="1" />
                    <x-button class="btn-primary btn-square shadow-primary/20 rounded-xl shadow-lg"
                        icon="o-paper-airplane" />
                </div>
            </div>
        </div>
    </x-drawer>

</div>