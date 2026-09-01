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
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
