{{--
    Le classement des poules, lisible depuis le bord du terrain.

    C'est la page qu'un joueur ouvre sur son téléphone pendant le tournoi : la
    sienne et celle de ses amis. Elle disait qui menait la poule, jamais où
    quelque chose se jouait — l'information était dans « Tables », c'est-à-dire
    derrière un autre onglet, trié par salle et non par poule.

    Deux endroits la portent maintenant : un bandeau en tête de poule pour la
    rencontre en cours, et une pastille sur la ligne des deux joueurs concernés
    pour qu'on retrouve un nom en balayant le classement.
--}}
<div @if($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif class="mt-6">
    @if ($this->pools->isEmpty())
        <div class="flex flex-col items-center py-20 text-muted">
            <x-icon name="o-user-group" class="mb-3 h-12 w-12" />
            <p class="text-sm">{{ __('No pools generated yet.') }}</p>
        </div>
    @else
        @php $placements = $this->livePlacements; @endphp

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            @foreach ($this->pools as $pool)
                <x-card wire:key="pool-{{ $pool['id'] }}"
                    title="{{ $pool['name'] }}"
                    shadow compact class="border-0">

                    <x-slot:menu>
                        @if ($pool['finished'])
                            <x-badge value="{{ __('Finished') }}" class="badge-success badge-sm" />
                        @elseif ($pool['live']->isNotEmpty())
                            <x-badge value="{{ __('Live') }}" class="badge-error badge-sm" />
                        @else
                            <x-badge value="{{ __('In progress') }}" class="badge-warning badge-sm" />
                        @endif
                    </x-slot:menu>

                    {{-- La rencontre en cours, en tête : la réponse à « où je joue ? ». --}}
                    @foreach ($pool['live'] as $live)
                        @php
                            $liveMatch = $live['match'];
                            $isDoubles = $liveMatch->pair1_id !== null;
                            $liveSide1 = $isDoubles ? ($liveMatch->pair1?->displayName() ?? '—') : ($liveMatch->player1?->full_name ?? '—');
                            $liveSide2 = $isDoubles ? ($liveMatch->pair2?->displayName() ?? '—') : ($liveMatch->player2?->full_name ?? '—');
                        @endphp

                        <div wire:key="pool-{{ $pool['id'] }}-live-{{ $liveMatch->id }}"
                            class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-error/30 bg-error/5 px-3 py-2">
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold">
                                {{ $liveSide1 }}
                                <span class="mx-1 text-xs font-black italic opacity-30">VS</span>
                                {{ $liveSide2 }}
                            </p>

                            <x-admin.club-events.tournaments.partials.live.live-badge
                                :table="$live['table']" :room="$live['room']" :started-at="$live['startedAt']" />
                        </div>
                    @endforeach

                    <div>
                        <div class="mb-1 flex justify-between border-b border-base-300 pb-1 text-xs font-bold text-muted">
                            <span class="flex-1">{{ __('Player') }}</span>
                            <div class="flex gap-5">
                                <span class="w-8 text-right">{{ __('W') }}</span>
                                <span class="w-8 text-right">{{ __('Sets') }}</span>
                                <span class="w-8 text-right">{{ __('Pts') }}</span>
                            </div>
                        </div>

                        @foreach ($pool['players'] as $i => $entry)
                            @php
                                $hasPair     = isset($entry['pair']);
                                $displayName = $hasPair ? $entry['pair']->displayName() : ($entry['player']->full_name ?? '—');
                                $entryUserId = $entry['player']->id ?? null;
                                $isMe        = $hasPair
                                    ? (auth()->id() === $entry['pair']->player1_id || auth()->id() === $entry['pair']->player2_id)
                                    : ($entryUserId === auth()->id());
                                // Une paire est en piste dès que l'un de ses deux joueurs l'est.
                                $playing = $hasPair
                                    ? ($placements[$entry['pair']->player1_id] ?? $placements[$entry['pair']->player2_id] ?? null)
                                    : ($entryUserId !== null ? ($placements[$entryUserId] ?? null) : null);
                            @endphp
                            <div wire:key="pool-{{ $pool['id'] }}-player-{{ $entryUserId ?? $i }}"
                                @class([
                                    'flex items-center justify-between border-b border-base-300/30 py-1.5',
                                    'text-primary font-semibold' => $isMe,
                                    'bg-error/5' => $playing !== null,
                                ])>
                                <div class="flex min-w-0 flex-1 items-center gap-2">
                                    <span class="w-4 shrink-0 font-mono text-xs text-muted">{{ $i + 1 }}</span>
                                    <span @class([
                                        'truncate text-sm font-medium',
                                        'line-through text-muted' => $entry['no_show'],
                                    ])>{{ $displayName }}</span>
                                    @if ($entry['no_show'])
                                        <x-badge value="{{ __('Forfeit') }}" class="badge-error badge-xs shrink-0" />
                                    @elseif ($playing)
                                        <x-admin.club-events.tournaments.partials.live.live-badge
                                            :table="$playing['table']" compact />
                                    @elseif ($isMe)
                                        <x-icon name="o-arrow-left" class="ml-1 h-3 w-3 shrink-0" />
                                    @endif
                                </div>
                                <div class="flex shrink-0 items-center gap-5">
                                    <span class="w-8 text-right font-mono text-sm">{{ $entry['matches_won'] }}</span>
                                    <span class="w-8 text-right font-mono text-sm opacity-60">{{ $entry['sets_won'] }}</span>
                                    <span class="w-8 text-right text-sm font-bold">{{ $entry['total_points'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
