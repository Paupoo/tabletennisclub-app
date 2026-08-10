@php
    $rounds = $this->knockoutMatches;
    $roundLabels = [
        'round_16' => __('Round of 16'),
        'quarterfinal' => __('Quarter-finals'),
        'semifinal' => __('Semi-finals'),
        'final' => __('Final'),
        'bronze' => __('3rd Place'),
    ];
    $activeRounds = array_filter($rounds, fn($matches) => $matches->count() > 0);
@endphp

<div @if ($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif class="mt-6">
    @if (empty($activeRounds))
        <div class="flex flex-col items-center py-20 text-muted">
            <x-icon name="o-trophy" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('Bracket not yet generated.') }}</p>
        </div>
    @else
        {{-- Bracket: horizontal scroll, all rounds except bronze --}}
        <div class="flex gap-8 overflow-x-auto pb-10" style="min-height: 600px;">

            @foreach (['round_16', 'quarterfinal', 'semifinal', 'final'] as $round)
                @if (isset($rounds[$round]) && $rounds[$round]->count() > 0)
                    <div class="flex flex-col min-w-[200px]">
                        <div
                            class="text-center font-bold text-base-content/40 uppercase text-xs tracking-widest h-8 mb-2">
                            {{ $roundLabels[$round] ?? $round }}
                        </div>

                        <div class="flex flex-col justify-around flex-grow gap-3">
                            @foreach ($rounds[$round] as $match)
                                @php
                                    $isDoubles = $match->pair1_id !== null;
                                    $p1Won = $match->winner_id === $match->player1_id;
                                    $p2Won = $match->winner_id === $match->player2_id;
                                    $isFinal = $round === 'final';
                                    $side1Name = $isDoubles
                                        ? $match->pair1?->displayName() ?? '—'
                                        : $match->player1?->full_name ?? '—';
                                    $side2Name = $isDoubles
                                        ? $match->pair2?->displayName() ?? '—'
                                        : $match->player2?->full_name ?? '—';
                                    $winnerName = $isDoubles
                                        ? ($p1Won
                                            ? $match->pair1?->displayName()
                                            : $match->pair2?->displayName())
                                        : $match->winner?->full_name;
                                @endphp
                                <div wire:key="bracket-{{ $match->id }}" @class([
                                    'p-3 rounded-xl shadow space-y-2 relative border-2',
                                    'border-yellow-500 bg-base-100 scale-105 shadow-xl' => $isFinal,
                                    'border-primary/30 bg-base-100' =>
                                        !$isFinal && $match->status === 'in_progress',
                                    'border-base-300 bg-base-100' =>
                                        !$isFinal && $match->status !== 'in_progress',
                                ])>

                                    {{-- Side 1 --}}
                                    <div @class([
                                        'flex justify-between items-center text-sm gap-2',
                                        'font-bold text-success' => $p1Won,
                                        'text-muted line-through' => $p2Won,
                                    ])>
                                        <span class="truncate">{{ $side1Name }}</span>
                                        <span
                                            class="font-mono shrink-0">{{ $match->getSetsWon($match->player1_id ?? 0) }}</span>
                                    </div>

                                    <div class="border-t border-base-300/50"></div>

                                    {{-- Side 2 --}}
                                    <div @class([
                                        'flex justify-between items-center text-sm gap-2',
                                        'font-bold text-success' => $p2Won,
                                        'text-muted line-through' => $p1Won,
                                    ])>
                                        <span class="truncate">{{ $side2Name }}</span>
                                        <span
                                            class="font-mono shrink-0">{{ $match->getSetsWon($match->player2_id ?? 0) }}</span>
                                    </div>

                                    @if ($match->referee)
                                        <div
                                            class="flex items-center gap-1 text-xs text-muted pt-1 border-t border-base-300/40">
                                            <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                                            <span class="truncate">{{ $match->referee->full_name }}</span>
                                        </div>
                                    @elseif (in_array($round, ['final', 'bronze']) && $match->player1_id)
                                        <div
                                            class="flex items-center gap-1 text-xs text-muted pt-1 border-t border-base-300/40 italic">
                                            <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                                            <span>{{ __('Organisation') }}</span>
                                        </div>
                                    @elseif ($match->status === 'scheduled' && $match->player1_id)
                                        <div
                                            class="flex items-center gap-1 text-xs text-muted pt-1 border-t border-base-300/40 italic">
                                            <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                                            <span>{{ __('Referee needed') }}</span>
                                        </div>
                                    @endif

                                    @if ($isFinal && $match->status === 'completed')
                                        <div class="text-center text-xs font-bold text-yellow-500 mt-1">
                                            🏆 {{ $winnerName }}
                                        </div>
                                    @endif

                                    {{-- Connector --}}
                                    @if ($round !== 'final')
                                        <div class="absolute -right-4 top-1/2 w-4 h-px bg-base-300"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

        </div>

        {{-- Bronze match below --}}
        @if (isset($rounds['bronze']) && $rounds['bronze']->count() > 0)
            @php
                $bronze = $rounds['bronze']->first();
                $b1Won = $bronze->winner_id === $bronze->player1_id;
                $b2Won = $bronze->winner_id === $bronze->player2_id;
                $bDoubles = $bronze->pair1_id !== null;
                $b1Name = $bDoubles ? $bronze->pair1?->displayName() ?? '—' : $bronze->player1?->full_name ?? '—';
                $b2Name = $bDoubles ? $bronze->pair2?->displayName() ?? '—' : $bronze->player2?->full_name ?? '—';
            @endphp
            <div class="mt-6 max-w-xs border-2 border-info rounded-xl p-4 space-y-2 shadow">
                <div class="text-center font-bold text-info uppercase text-xs tracking-widest mb-3">
                    {{ __('3rd Place') }}
                </div>
                <div @class([
                    'flex justify-between text-sm',
                    'font-bold text-success' => $b1Won,
                    'text-muted' => $b2Won,
                ])>
                    <span class="truncate">{{ $b1Name }}</span>
                    <span class="font-mono">{{ $bronze->getSetsWon($bronze->player1_id ?? 0) }}</span>
                </div>
                <div class="border-t border-base-300/50"></div>
                <div @class([
                    'flex justify-between text-sm',
                    'font-bold text-success' => $b2Won,
                    'text-muted' => $b1Won,
                ])>
                    <span class="truncate">{{ $b2Name }}</span>
                    <span class="font-mono">{{ $bronze->getSetsWon($bronze->player2_id ?? 0) }}</span>
                </div>

                @if ($match->referee)
                    <div class="flex items-center gap-1 text-xs text-muted pt-1 border-t border-base-300/40">
                        <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                        <span class="truncate">{{ $match->referee->full_name }}</span>
                    </div>
                @elseif (in_array($round, ['final', 'bronze']) && $match->player1_id)
                    <div class="flex items-center gap-1 text-xs text-muted pt-1 border-t border-base-300/40 italic">
                        <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                        <span>{{ __('Organisation') }}</span>
                    </div>
                @elseif ($match->status === 'scheduled' && $match->player1_id)
                    <div class="flex items-center gap-1 text-xs text-muted pt-1 border-t border-base-300/40 italic">
                        <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                        <span>{{ __('Referee needed') }}</span>
                    </div>
                @endif

                @if ($isFinal && $match->status === 'completed')
                    <div class="text-center text-xs font-bold text-yellow-500 mt-1">
                        🏆 {{ $winnerName }}
                    </div>
                @endif
            </div>
        @endif

        {{-- Close tournament button
        @if ($this->bracketPhaseComplete && !$this->tournamentClosed)
            <div class="mt-12 flex justify-center">
                <x-button :label="__('Close tournament')" icon="o-flag"
                    class="btn-error btn-outline"
                    wire:click="closeTournament" wire:confirm="__('This will mark the tournament as closed. Continue?')" />
            </div>
        @endif --}}
    @endif
</div>
