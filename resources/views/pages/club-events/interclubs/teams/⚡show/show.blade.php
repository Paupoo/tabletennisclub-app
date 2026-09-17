<div>
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator separator
        :title="($team->club?->name ?? '') . ' ' . $team->name">
        <x-slot:actions>
            <x-button class="btn-ghost" link="{{ route('admin.interclubs.teams') }}" icon="o-arrow-left"
                :label="__('All teams')" />
            @can('update', $team)
                <x-button class="btn-primary" link="{{ route('admin.interclubs.teams.edit', $team->id) }}"
                    icon="o-pencil" label="Modifier" />
            @endcan
        </x-slot:actions>
    </x-header>

    {{-- ── Fiche équipe ─────────────────────────────────────────────────── --}}
    <div class="mb-8 grid gap-5 lg:grid-cols-3">

        {{-- Infos générales --}}
        <x-card class="shadow-sm lg:col-span-1">
            <div class="space-y-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-100 text-2xl font-bold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                        {{ $team->name }}
                    </div>
                    <div>
                        <p class="text-lg font-bold text-base-content">
                            {{ ($team->club?->name ?? '') . ' ' . $team->name }}
                        </p>
                        <span class="rounded-full bg-base-200 px-2 py-0.5 text-xs font-medium text-muted">
                            {{ $category }}
                        </span>
                    </div>
                </div>

                <div class="divide-y divide-base-300">
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Division</span>
                        <span class="text-sm font-medium text-base-content">{{ $division }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Saison</span>
                        <span class="text-sm font-medium text-base-content">{{ $team->season?->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Capitaine</span>
                        <span class="text-sm font-semibold text-base-content">
                            {{ $team->captain ? $team->captain->first_name . ' ' . $team->captain->last_name : '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-3">
                        <span class="text-xs uppercase tracking-wide text-gray-400">Noyau</span>
                        <span class="text-sm font-medium text-base-content">{{ $team->users->count() }} joueurs</span>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Noyau de l'équipe --}}
        <x-card class="shadow-sm lg:col-span-2" title="Noyau">
            @if ($team->users->isEmpty())
                <p class="py-6 text-center text-sm text-gray-400 italic">Aucun joueur dans le noyau.</p>
            @else
                <div class="divide-y divide-base-300">
                    @foreach ($team->users->sortBy(fn ($user) => sprintf('%03d|%s|%s', $user->forceListFor($team->league?->category) ?? 999, $user->last_name, $user->first_name)) as $user)
                        <div class="flex items-center justify-between py-3" wire:key="member-{{ $user->id }}">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-base-200 text-xs font-semibold text-muted">
                                    {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}{{ mb_strtoupper(mb_substr($user->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-base-content">
                                        {{ $user->first_name }} {{ $user->last_name }}
                                        @if ($team->captain_id === $user->id)
                                            <span class="ml-1 rounded bg-yellow-100 px-1.5 py-0.5 text-xs font-semibold text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300">C</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($user->ranking)
                                    <span class="rounded bg-base-200 px-2 py-0.5 text-xs font-semibold text-muted">
                                        {{ $user->ranking->getLabel() }}
                                    </span>
                                @endif
                                @if ($user->is_competitor)
                                    <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-300">{{ __('Competitor') }}</span>
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
        <x-card class="mb-6 shadow-sm" title="Prochains matchs">
            <div class="divide-y divide-base-300">
                @foreach ($upcomingInterclubs as $ic)
                    @php
                        $isHome   = $ic->visited_team_id === $team->id;
                        $opponent = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
                    @endphp
                    <div class="flex items-center justify-between py-3" wire:key="upcoming-{{ $ic->id }}">
                        <div class="flex items-center gap-3">
                            <span class="rounded px-2 py-0.5 text-xs font-bold uppercase
                                {{ $isHome ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-base-200 text-muted' }}">
                                {{ $isHome ? 'Dom.' : 'Ext.' }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-base-content">
                                    {{ $opponent?->club?->name ?? 'Adversaire' }}
                                    {{ $opponent?->name ?? '' }}
                                </p>
                                @if ($ic->address)
                                    <p class="text-xs text-gray-400">{{ $ic->address }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="text-sm text-muted">
                            {{ \Carbon\Carbon::parse($ic->start_date_time)->translatedFormat('D d M · H\hi') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- ── Résultats ────────────────────────────────────────────────────── --}}
    {{--
        Lisait `interclubs.score` et comparait `interclubs.result` à 'W'/'D'/'L'.
        La colonne n'est écrite par rien et l'enum vaut Win/Loss/Draw : la carte
        n'aurait jamais rien affiché, même remplie. Le score vit sur
        `interclub_results`, écrit par l'écran des résultats.
    --}}
    <x-card class="shadow-sm" :title="__('Results')">
        @if ($pastInterclubs->isEmpty())
            <x-empty-state icon="o-trophy" :heading="__('No match played yet this season.')" />
        @else
            <div class="divide-y divide-base-300">
                @foreach ($pastInterclubs as $ic)
                    @php
                        $isHome = $ic->visited_team_id === $team->id;
                        $opponent = $isHome ? $ic->visitingTeam : $ic->visitedTeam;
                        $matchResult = $ic->interclubResult;

                        // Le score est stocké domicile en premier : à l'extérieur
                        // il se lit à l'envers pour l'équipe concernée.
                        $score = null;
                        if ($matchResult?->score && str_contains($matchResult->score, '-')) {
                            [$h, $a] = array_map(intval(...), explode('-', $matchResult->score, 2));
                            $score = $isHome ? "{$h}-{$a}" : "{$a}-{$h}";
                        }

                        [$letter, $tone] = match ($matchResult?->result?->value) {
                            'Win', 'ForfeitWin', 'WithdrawalOpponent' => ['V', 'bg-success/15 text-success'],
                            'Draw' => ['P', 'bg-base-200 text-muted'],
                            'Loss', 'ForfeitLoss', 'Withdrawal' => ['D', 'bg-error/15 text-error'],
                            default => [null, ''],
                        };
                    @endphp
                    <div class="flex items-center justify-between py-3" wire:key="result-{{ $ic->id }}">
                        <div class="flex min-w-0 items-center gap-3">
                            @if ($letter)
                                <span class="w-6 shrink-0 rounded text-center text-xs font-bold {{ $tone }}">{{ $letter }}</span>
                            @else
                                <span class="w-6 shrink-0"></span>
                            @endif
                            <div class="min-w-0">
                                <a href="{{ route('admin.interclubs.my-match', $ic) }}"
                                    class="link link-hover truncate text-sm font-medium text-base-content">
                                    {{ $opponent?->club?->name ?? __('Opponent') }} {{ $opponent?->name ?? '' }}
                                </a>
                                <p class="text-xs text-base-content/50">{{ $isHome ? __('Home') : __('Away') }}</p>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-semibold text-base-content">{{ $score ?? '—' }}</p>
                            <p class="text-xs text-base-content/50">
                                {{ $ic->start_date_time->translatedFormat('d M') }}
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</div>
