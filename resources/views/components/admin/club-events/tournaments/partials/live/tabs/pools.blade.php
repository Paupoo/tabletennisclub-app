{{--
    Le classement des poules, lisible depuis le bord du terrain.

    C'est la page qu'un joueur ouvre sur son téléphone pendant le tournoi. Elle
    disait qui menait la poule et jamais où quelque chose se jouait : l'info
    vivait dans l'onglet « Tables », trié par salle, c'est-à-dire à l'endroit
    où on ne cherche pas un nom.

    Un numéro de table à côté du joueur, et rien d'autre. Une première version
    ajoutait un bandeau par poule et une pastille rouge clignotante ; ça noyait
    le classement, qui doit rester un classement.
--}}
<div @if($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif class="mt-6">
    @if ($this->pools->isEmpty())
        <div class="flex flex-col items-center py-20 text-muted">
            <x-icon name="o-user-group" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('No pools generated yet.') }}</p>
        </div>
    @else
        @php $placements = $this->livePlacements; @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($this->pools as $pool)
                <x-card wire:key="pool-{{ $pool['id'] }}"
                    title="{{ $pool['name'] }}"
                    shadow compact class="border-0">

                    <x-slot:menu>
                        @if ($pool['finished'])
                            <x-badge value="{{ __('Finished') }}" class="badge-success badge-sm" />
                        @else
                            <x-badge value="{{ __('In progress') }}" class="badge-warning badge-sm" />
                        @endif
                    </x-slot:menu>

                    <div>
                        <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 text-muted text-xs">
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
                                    'flex justify-between items-center border-b border-base-300/30 py-1.5',
                                    'text-primary font-semibold' => $isMe,
                                ])>
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <span class="text-xs font-mono text-muted w-4 shrink-0">{{ $i + 1 }}</span>
                                    <span @class([
                                        'truncate text-sm font-medium',
                                        'line-through text-muted' => $entry['no_show'],
                                    ])>{{ $displayName }}</span>
                                    @if ($entry['no_show'])
                                        <x-badge value="{{ __('Forfeit') }}" class="badge-error badge-xs shrink-0" />
                                    @elseif ($playing)
                                        <x-badge value="{{ __('Table :name', ['name' => $playing['table']]) }}"
                                            class="badge-primary badge-soft badge-xs shrink-0 font-semibold" />
                                    @elseif ($isMe)
                                        <x-icon name="o-arrow-left" class="w-3 h-3 ml-1 shrink-0" />
                                    @endif
                                </div>
                                <div class="flex gap-5 items-center shrink-0">
                                    <span class="w-8 text-right font-mono text-sm">{{ $entry['matches_won'] }}</span>
                                    <span class="w-8 text-right font-mono text-sm opacity-60">{{ $entry['sets_won'] }}</span>
                                    <span class="w-8 text-right font-bold text-sm">{{ $entry['total_points'] }}</span>
                                    @if (($manage ?? false) && ! $entry['no_show'] && $entryUserId !== null && $tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING)
                                        <x-button icon="o-flag" class="btn-ghost btn-xs btn-square text-base-content/40"
                                            :tooltip="__('Withdraw')" :aria-label="__('Withdraw :name', ['name' => $displayName])"
                                            wire:click="withdraw({{ $entryUserId }})"
                                            wire:confirm="{{ __(':name withdraws: every match still to play is lost, those played stand. Continue?', ['name' => $displayName]) }}" />
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Les matchs joués, pour corriger un score tant que le tableau n'est pas tiré. --}}
                    @if (($manage ?? false) && ! $this->bracketExists && ($this->playedPoolMatches[$pool['id']] ?? collect())->isNotEmpty())
                        <details class="mt-3 text-xs" data-played-matches>
                            <summary class="cursor-pointer text-base-content/60">{{ __('Matches played') }}</summary>
                            <ul class="mt-2 space-y-1">
                                @foreach ($this->playedPoolMatches[$pool['id']] as $played)
                                    @php
                                        $playedDoubles = $played->pair1_id !== null;
                                        $playedSide1 = $playedDoubles ? ($played->pair1?->displayName() ?? '—') : ($played->player1?->full_name ?? '—');
                                        $playedSide2 = $playedDoubles ? ($played->pair2?->displayName() ?? '—') : ($played->player2?->full_name ?? '—');
                                    @endphp
                                    <li wire:key="played-{{ $played->id }}" class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1 truncate">{{ $playedSide1 }} — {{ $playedSide2 }}</span>
                                        <span class="shrink-0 font-mono">
                                            @if ($played->is_forfeit)
                                                {{ __('WO') }}
                                            @else
                                                {{ $played->sets->map(fn ($set) => $set->player1_score . '-' . $set->player2_score)->implode(' ') }}
                                            @endif
                                        </span>
                                        <x-button icon="o-pencil" class="btn-ghost btn-xs btn-square" :tooltip="__('Correct the score')"
                                            :aria-label="__('Correct the score')" wire:click="openScoreEntry({{ $played->id }})" />
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
