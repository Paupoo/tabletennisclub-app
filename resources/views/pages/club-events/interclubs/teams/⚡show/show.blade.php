<div>
    <x-header progress-indicator separator
        :title="($team->club?->name ?? '') . ' ' . $team->name">
        <x-slot:actions>
            <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams') }}" icon="o-arrow-left"
                label="Toutes les équipes" />
            <x-button class="btn-primary" link="{{ route('admin.interclubs.teams.edit', $team->id) }}"
                icon="o-pencil" label="Modifier" />
        </x-slot:actions>
    </x-header>

    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />

    {{-- ── Fiche équipe ─────────────────────────────────────────────────── --}}
    <div class="mb-8 grid gap-5 lg:grid-cols-3">

        {{-- Infos générales --}}
        <x-card class="border-gray-200 shadow-sm lg:col-span-1">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 text-2xl font-bold text-blue-800">
                        {{ $team->name }}
                    </div>
                    <div>
                        <p class="text-lg font-bold text-gray-900">
                            {{ ($team->club?->name ?? '') . ' ' . $team->name }}
                        </p>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">
                            {{ $category }}
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Division</span>
                        <span class="text-sm font-medium text-gray-800">{{ $division }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Saison</span>
                        <span class="text-sm font-medium text-gray-800">{{ $team->season?->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Capitaine</span>
                        <span class="text-sm font-semibold text-gray-900">
                            {{ $team->captain ? $team->captain->first_name . ' ' . $team->captain->last_name : '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Noyau</span>
                        <span class="text-sm font-medium text-gray-800">{{ $team->users->count() }} joueurs</span>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Noyau de l'équipe --}}
        <x-card class="border-gray-200 shadow-sm lg:col-span-2" title="Noyau">
            @if ($team->users->isEmpty())
                <p class="py-6 text-center text-sm text-gray-400 italic">Aucun joueur dans le noyau.</p>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach ($team->users->sortBy('force_list') as $user)
                        <div class="flex items-center justify-between py-3" wire:key="member-{{ $user->id }}">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                    {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}{{ mb_strtoupper(mb_substr($user->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $user->first_name }} {{ $user->last_name }}
                                        @if ($team->captain_id === $user->id)
                                            <span class="ml-1 rounded bg-yellow-100 px-1.5 py-0.5 text-[10px] font-semibold text-yellow-700">C</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($user->ranking)
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">
                                        {{ $user->ranking }}
                                    </span>
                                @endif
                                @if ($user->is_competitor)
                                    <span class="rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">Compétiteur</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Prochains matchs ─────────────────────────────────────────────── --}}
    @if ($upcomingInterclubs->isNotEmpty())
        <x-card class="mb-6 border-gray-200 shadow-sm" title="Prochains matchs">
            <div class="divide-y divide-gray-100">
                @foreach ($upcomingInterclubs as $ic)
                    @php
                        $isHome   = $ic->visited_team_id === $team->id;
                        $opponent = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
                    @endphp
                    <div class="flex items-center justify-between py-3" wire:key="upcoming-{{ $ic->id }}">
                        <div class="flex items-center gap-3">
                            <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase
                                {{ $isHome ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $isHome ? 'Dom.' : 'Ext.' }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $opponent?->club?->name ?? 'Adversaire' }}
                                    {{ $opponent?->name ?? '' }}
                                </p>
                                @if ($ic->address)
                                    <p class="text-xs text-gray-400">{{ $ic->address }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm text-gray-600">
                            {{ \Carbon\Carbon::parse($ic->start_date_time)->translatedFormat('D d M · H\hi') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- ── Résultats (mock) ─────────────────────────────────────────────── --}}
    <x-card class="border-gray-200 shadow-sm" title="Résultats">
        <x-slot:subtitle>
            <span class="text-xs text-orange-500">Module résultats à venir — données simulées</span>
        </x-slot:subtitle>

        @if ($pastInterclubs->isEmpty())
            {{-- Pas encore de matchs joués → résultats mock pur --}}
            @php
                $mockResults = [
                    ['date' => now()->subDays(7),  'opponent' => 'Perwez A',        'home' => true,  'score' => '6 – 2', 'result' => 'W'],
                    ['date' => now()->subDays(14), 'opponent' => 'Wavre C',         'home' => false, 'score' => '4 – 4', 'result' => 'D'],
                    ['date' => now()->subDays(21), 'opponent' => 'Logis J',         'home' => true,  'score' => '7 – 1', 'result' => 'W'],
                    ['date' => now()->subDays(28), 'opponent' => 'Auderghem E',     'home' => false, 'score' => '2 – 6', 'result' => 'L'],
                    ['date' => now()->subDays(35), 'opponent' => "Champ d'en Haut", 'home' => true,  'score' => '5 – 3', 'result' => 'W'],
                ];
            @endphp
            <div class="divide-y divide-gray-100">
                @foreach ($mockResults as $r)
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <span class="w-6 rounded text-center text-xs font-bold
                                {{ $r['result'] === 'W' ? 'bg-green-100 text-green-700' : ($r['result'] === 'D' ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700') }}">
                                {{ $r['result'] }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ $r['opponent'] }}</p>
                                <p class="text-xs text-gray-400">{{ $r['home'] ? 'Domicile' : 'Extérieur' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ $r['score'] }}</p>
                            <p class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($r['date'])->translatedFormat('d M') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            {{-- Vrais matchs joués --}}
            <div class="divide-y divide-gray-100">
                @foreach ($pastInterclubs as $ic)
                    @php
                        $isHome   = $ic->visited_team_id === $team->id;
                        $opponent = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
                    @endphp
                    <div class="flex items-center justify-between py-3" wire:key="result-{{ $ic->id }}">
                        <div class="flex items-center gap-3">
                            @if ($ic->result)
                                <span class="w-6 rounded text-center text-xs font-bold
                                    {{ $ic->result === 'W' ? 'bg-green-100 text-green-700' : ($ic->result === 'D' ? 'bg-gray-100 text-gray-600' : 'bg-red-100 text-red-700') }}">
                                    {{ $ic->result }}
                                </span>
                            @endif
                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ $opponent?->club?->name ?? 'Adversaire' }} {{ $opponent?->name ?? '' }}
                                </p>
                                <p class="text-xs text-gray-400">{{ $isHome ? 'Domicile' : 'Extérieur' }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-gray-900">{{ $ic->score ?? '—' }}</p>
                            <p class="text-xs text-gray-400">
                                {{ \Carbon\Carbon::parse($ic->start_date_time)->translatedFormat('d M') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
