{{--
    Le tournoi vu du bord du terrain, sur un téléphone.

    Un seul ordre de lecture, du plus urgent au moins : ce que je fais
    maintenant, quand je joue, ce qui se joue autour, les classements. Rien
    n'est cliquable qui n'ait besoin de l'être — la page ne sait rien écrire.
--}}
<div class="mx-auto max-w-3xl p-4">
    <x-slot:breadcrumbs>
        <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
    </x-slot:breadcrumbs>

    <x-header progress-indicator :title="$tournament->name" :subtitle="__('Follow the tournament live')">
        <x-slot:actions>
            @if ($this->tournamentIsLive)
                {{-- Pas la pastille de table : ici c'est le tournoi qui est en
                     cours, pas une rencontre sur un numéro de table. --}}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-error/10 px-2.5 py-1 text-sm font-semibold text-error">
                    <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-error opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-error"></span>
                    </span>
                    {{ __('Live') }}
                </span>
            @elseif ($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::CLOSED)
                <x-badge :value="__('Closed')" class="badge-neutral" icon="o-lock-closed" />
            @endif
        </x-slot:actions>
    </x-header>

    <div @if($this->tournamentIsLive) wire:poll.10s @endif>
        <x-tabs wire:model="activeTab" class="mb-6">

            <x-tab name="my-tournament" icon="o-user" :label="__('My tournament')">

                {{-- 1. Ce que je fais maintenant. --}}
                @if ($this->myLiveMatch)
                    @php
                        $live = $this->myLiveMatch;
                        $liveMatch = $live['match'];
                        $liveDoubles = $liveMatch->pair1_id !== null;
                    @endphp

                    <div class="mb-4 rounded-2xl border-2 border-error bg-error/5 p-5">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-error">
                            {{ __('You are playing now') }}
                        </p>
                        <p class="text-lg font-bold">
                            {{ $liveDoubles ? ($liveMatch->pair1?->displayName() ?? '—') : ($liveMatch->player1?->full_name ?? '—') }}
                            <span class="mx-1 text-sm font-black italic opacity-30">VS</span>
                            {{ $liveDoubles ? ($liveMatch->pair2?->displayName() ?? '—') : ($liveMatch->player2?->full_name ?? '—') }}
                        </p>
                        <div class="mt-3">
                            <x-admin.club-events.tournaments.partials.live.live-badge
                                :table="$live['table']" :room="$live['room']" :started-at="$live['startedAt']" />
                        </div>
                    </div>
                @endif

                {{-- 2. Quand je joue. Le compte, et rien de plus : une estimation
                     en minutes est une promesse que la salle ne peut pas tenir. --}}
                <x-card class="mb-4 border border-base-300" shadow>
                    <x-slot:title>
                        <span class="text-sm font-bold uppercase tracking-wide text-base-content/50">
                            {{ __('Your next match') }}
                        </span>
                    </x-slot:title>

                    @if ($this->myNextMatch)
                        @php
                            $next = $this->myNextMatch;
                            $nextMatch = $next['match'];
                            $ahead = $next['ahead'];
                            $nextDoubles = $nextMatch->pair1_id !== null;
                        @endphp

                        <div class="flex flex-col gap-3">
                            <x-badge
                                :value="$nextMatch->pool?->name ?? __('Bracket')"
                                class="{{ $nextMatch->pool_id ? 'badge-ghost' : 'badge-warning' }} badge-sm w-fit font-bold uppercase" />

                            <p class="text-lg font-bold leading-tight">
                                {{ $nextDoubles ? ($nextMatch->pair1?->displayName() ?? '—') : ($nextMatch->player1?->full_name ?? '—') }}
                                <span class="mx-1 text-sm font-black italic opacity-30">VS</span>
                                {{ $nextDoubles ? ($nextMatch->pair2?->displayName() ?? '—') : ($nextMatch->player2?->full_name ?? '—') }}
                            </p>

                            {{-- Sous les joueurs, comme dans la file : au-dessus, le nom
                                 de l'arbitre se lit comme celui d'un adversaire. --}}
                            @if ($nextMatch->referee)
                                <p class="flex items-center gap-1 text-xs text-muted">
                                    <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ __('Referee') }} · {{ $nextMatch->referee->full_name }}</span>
                                </p>
                            @endif

                            @if ($ahead === 0)
                                <div class="flex items-center gap-2 rounded-xl bg-warning/10 px-3 py-2 text-warning-content">
                                    <x-icon name="o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                                    <p class="text-sm font-bold">{{ __('You are next. Stay close to the tables.') }}</p>
                                </div>
                            @else
                                <p class="text-3xl font-black leading-none">
                                    {{ $ahead }}
                                    <span class="text-sm font-semibold text-muted">
                                        {{ trans_choice('{1} match before yours|[2,*] matches before yours', $ahead) }}
                                    </span>
                                </p>
                            @endif
                        </div>
                    @elseif ($this->myLiveMatch)
                        <p class="text-sm text-muted">{{ __('Nothing else scheduled while you are on the table.') }}</p>
                    @else
                        <div class="flex flex-col items-center py-6 text-muted">
                            <x-icon name="o-check-circle" class="mb-2 h-8 w-8" />
                            <p class="text-sm">{{ __('No match scheduled for you right now.') }}</p>
                        </div>
                    @endif
                </x-card>

                {{-- 3. Ce qui se joue autour : où sont les autres. --}}
                <x-card class="mb-4 border border-base-300" shadow>
                    <x-slot:title>
                        <span class="text-sm font-bold uppercase tracking-wide text-base-content/50">
                            {{ __('Being played now') }}
                        </span>
                    </x-slot:title>

                    @if ($this->liveMatches->isEmpty())
                        <p class="py-4 text-center text-sm text-muted">{{ __('No match in progress.') }}</p>
                    @else
                        <div class="flex flex-col gap-2">
                            @foreach ($this->liveMatches as $live)
                                @php
                                    $m = $live['match'];
                                    $d = $m->pair1_id !== null;
                                @endphp
                                <div wire:key="live-{{ $m->id }}"
                                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-base-300 px-3 py-2">
                                    <p class="min-w-0 flex-1 truncate text-sm font-semibold">
                                        {{ $d ? ($m->pair1?->displayName() ?? '—') : ($m->player1?->full_name ?? '—') }}
                                        <span class="mx-1 text-xs font-black italic opacity-30">VS</span>
                                        {{ $d ? ($m->pair2?->displayName() ?? '—') : ($m->player2?->full_name ?? '—') }}
                                    </p>
                                    <x-admin.club-events.tournaments.partials.live.live-badge
                                        :table="$live['table']" :room="$live['room']" :started-at="$live['startedAt']" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-card>

                {{-- 4. La file, pour compter soi-même. --}}
                <x-card class="mb-4 border border-base-300" shadow>
                    <x-slot:title>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-bold uppercase tracking-wide text-base-content/50">
                                {{ __('Match queue') }}
                            </span>
                            @if ($this->queue->isNotEmpty())
                                <x-badge :value="$this->queue->count()" class="badge-ghost badge-sm ml-auto" />
                            @endif
                        </div>
                    </x-slot:title>

                    @if ($this->queue->isEmpty())
                        <p class="py-4 text-center text-sm text-muted">{{ __('All matches are done or in progress.') }}</p>
                    @else
                        <ol class="flex flex-col gap-1.5">
                            @foreach ($this->queue as $i => $match)
                                @php
                                    $d = $match->pair1_id !== null;
                                    $mine = $this->myNextMatch && $this->myNextMatch['match']->is($match);
                                @endphp
                                <li wire:key="queue-{{ $match->id }}" @class([
                                    'flex items-center gap-3 rounded-xl border px-3 py-2',
                                    'border-primary bg-primary/5' => $mine,
                                    'border-base-300' => ! $mine,
                                ])>
                                    <span class="w-5 shrink-0 text-center font-mono text-xs text-muted">{{ $i + 1 }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p @class(['truncate text-sm', 'font-bold text-primary' => $mine, 'font-medium' => ! $mine])>
                                            {{ $d ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—') }}
                                            <span class="mx-1 text-xs font-black italic opacity-30">VS</span>
                                            {{ $d ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—') }}
                                        </p>
                                        <p class="text-xs text-muted">{{ $match->pool?->name ?? __('Bracket') }}</p>
                                    </div>
                                    @if ($mine)
                                        <x-badge :value="__('You')" class="badge-primary badge-sm shrink-0" />
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>

                {{-- 5. Ce que j'ai déjà joué. --}}
                @if ($this->myPlayedMatches->isNotEmpty())
                    <x-card class="border border-base-300" shadow>
                        <x-slot:title>
                            <span class="text-sm font-bold uppercase tracking-wide text-base-content/50">
                                {{ __('Your results') }}
                            </span>
                        </x-slot:title>

                        <div class="flex flex-col gap-1.5">
                            @foreach ($this->myPlayedMatches as $match)
                                @php
                                    $d = $match->pair1_id !== null;
                                    $won = $match->winner_id !== null
                                        && $match->sidePlayerIds($match->winner_id === $match->player1_id ? 1 : 2)->contains(auth()->id());
                                @endphp
                                <div wire:key="played-{{ $match->id }}"
                                    class="flex items-center justify-between gap-3 border-b border-base-300/30 py-2 last:border-0">
                                    <p class="min-w-0 flex-1 truncate text-sm">
                                        {{ $d ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—') }}
                                        <span class="mx-1 text-xs font-black italic opacity-30">VS</span>
                                        {{ $d ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—') }}
                                    </p>
                                    <x-badge :value="$won ? __('Won') : __('Lost')"
                                        class="{{ $won ? 'badge-success' : 'badge-ghost' }} badge-sm shrink-0" />
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            </x-tab>

            <x-tab name="pools" icon="o-user-group" :label="__('Pools')">
                <x-admin.club-events.tournaments.partials.live.tabs.pools :tournament="$tournament" />
            </x-tab>

        </x-tabs>
    </div>
</div>
