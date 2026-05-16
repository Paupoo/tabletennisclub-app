@php
    $rounds = $this->knockoutMatches;
    $roundLabels = [
        'round_16'    => __('Round of 16'),
        'quarterfinal' => __('Quarter-finals'),
        'semifinal'   => __('Semi-finals'),
        'final'       => __('Final'),
        'bronze'      => __('3rd Place'),
    ];
    $activeRounds = array_filter($rounds, fn ($matches) => $matches->count() > 0);
@endphp

<div class="mt-6">
    @if (empty($activeRounds))
        <div class="flex flex-col items-center py-20 opacity-30">
            <x-icon name="o-trophy" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('Bracket not yet generated.') }}</p>
        </div>
    @else
        {{-- Bracket: horizontal scroll, all rounds except bronze --}}
        <div class="flex gap-8 overflow-x-auto pb-10" style="min-height: 600px;">

            @foreach (['round_16', 'quarterfinal', 'semifinal', 'final'] as $round)
                @if (isset($rounds[$round]) && $rounds[$round]->count() > 0)
                    <div class="flex flex-col min-w-[200px]">
                        <div class="text-center font-bold text-base-content/40 uppercase text-[10px] tracking-widest h-8 mb-2">
                            {{ $roundLabels[$round] ?? $round }}
                        </div>

                        <div class="flex flex-col justify-around flex-grow gap-3">
                            @foreach ($rounds[$round] as $match)
                                @php
                                    $p1Won = $match->winner_id === $match->player1_id;
                                    $p2Won = $match->winner_id === $match->player2_id;
                                    $isFinal = $round === 'final';
                                @endphp
                                <div wire:key="bracket-{{ $match->id }}"
                                    @class([
                                        'p-3 rounded-xl shadow space-y-2 relative border-2',
                                        'border-yellow-500 bg-base-100 scale-105 shadow-xl' => $isFinal,
                                        'border-primary/30 bg-base-100' => !$isFinal && $match->status === 'in_progress',
                                        'border-base-300 bg-base-100' => !$isFinal && $match->status !== 'in_progress',
                                    ])>

                                    {{-- Player 1 --}}
                                    <div @class([
                                        'flex justify-between items-center text-sm gap-2',
                                        'font-bold text-success' => $p1Won,
                                        'opacity-40 line-through' => $p2Won,
                                    ])>
                                        <span class="truncate">{{ $match->player1?->full_name ?? '—' }}</span>
                                        <span class="font-mono shrink-0">{{ $match->getSetsWon($match->player1_id ?? 0) }}</span>
                                    </div>

                                    <div class="border-t border-base-300/50"></div>

                                    {{-- Player 2 --}}
                                    <div @class([
                                        'flex justify-between items-center text-sm gap-2',
                                        'font-bold text-success' => $p2Won,
                                        'opacity-40 line-through' => $p1Won,
                                    ])>
                                        <span class="truncate">{{ $match->player2?->full_name ?? '—' }}</span>
                                        <span class="font-mono shrink-0">{{ $match->getSetsWon($match->player2_id ?? 0) }}</span>
                                    </div>

                                    @if ($isFinal && $match->status === 'completed')
                                        <div class="text-center text-xs font-bold text-yellow-500 mt-1">
                                            🏆 {{ $match->winner?->full_name }}
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
            @php $bronze = $rounds['bronze']->first(); $b1Won = $bronze->winner_id === $bronze->player1_id; $b2Won = $bronze->winner_id === $bronze->player2_id; @endphp
            <div class="mt-6 max-w-xs border-2 border-info rounded-xl p-4 space-y-2 shadow">
                <div class="text-center font-bold text-info uppercase text-[10px] tracking-widest mb-3">
                    {{ __('3rd Place') }}
                </div>
                <div @class(['flex justify-between text-sm', 'font-bold text-success' => $b1Won, 'opacity-40' => $b2Won])>
                    <span class="truncate">{{ $bronze->player1?->full_name ?? '—' }}</span>
                    <span class="font-mono">{{ $bronze->getSetsWon($bronze->player1_id ?? 0) }}</span>
                </div>
                <div class="border-t border-base-300/50"></div>
                <div @class(['flex justify-between text-sm', 'font-bold text-success' => $b2Won, 'opacity-40' => $b1Won])>
                    <span class="truncate">{{ $bronze->player2?->full_name ?? '—' }}</span>
                    <span class="font-mono">{{ $bronze->getSetsWon($bronze->player2_id ?? 0) }}</span>
                </div>
            </div>
        @endif

        {{-- Close tournament button
        @if ($this->bracketPhaseComplete && ! $this->tournamentClosed)
            <div class="mt-12 flex justify-center">
                <x-button label="{{ __('Close tournament') }}" icon="o-flag"
                    class="btn-error btn-outline"
                    wire:click="closeTournament" wire:confirm="{{ __('This will mark the tournament as closed. Continue?') }}" />
            </div>
        @endif --}}
    @endif
</div>
