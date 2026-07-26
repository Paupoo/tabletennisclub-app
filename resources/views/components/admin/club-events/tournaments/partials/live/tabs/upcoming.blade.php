<div @if($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif class="mt-6">

    @if ($this->upcomingMatches->isEmpty())
        <div class="flex flex-col items-center py-20 opacity-30">
            <x-icon name="o-check-circle" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('All matches are done or in progress.') }}</p>
        </div>
    @else
        <div class="flex flex-col gap-3 lg:max-w-2xl">
            @foreach ($this->upcomingMatches as $index => $match)
                @php
                    $nc           = \App\Domains\Shared\Enums\Ranking::NC->value;
                    $busyIds      = $this->busyPlayerIds;
                    $isDoubles    = $match->pair1_id !== null;
                    $isPool       = $match->pool_id !== null;
                    $isReady      = $match->player1_id !== null && $match->player2_id !== null;
                    $side1Ids     = $isDoubles
                        ? collect([$match->pair1?->player1_id, $match->pair1?->player2_id])->filter()
                        : collect([$match->player1_id])->filter();
                    $side2Ids     = $isDoubles
                        ? collect([$match->pair2?->player1_id, $match->pair2?->player2_id])->filter()
                        : collect([$match->player2_id])->filter();
                    $hasConflict  = $isReady && ($side1Ids->intersect($busyIds)->isNotEmpty() || $side2Ids->intersect($busyIds)->isNotEmpty());
                    $side1Name    = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
                    $side2Name    = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
                    $side1Rank    = $isDoubles ? ($match->pair1?->rankingLabel() ?? $nc) : ($match->player1?->ranking ?? $nc);
                    $side2Rank    = $isDoubles ? ($match->pair2?->rankingLabel() ?? $nc) : ($match->player2?->ranking ?? $nc);
                    $refereeName  = $match->referee?->full_name;
                    $label        = $isPool
                        ? ($match->pool?->name ?? __('Pool'))
                        : match($match->round) {
                            'round_16'    => __('Round of 16'),
                            'quarterfinal'=> __('Quarterfinal'),
                            'semifinal'   => __('Semifinal'),
                            'final'       => __('Final'),
                            'bronze'      => __('3rd place'),
                            default       => __('Bracket'),
                          };
                @endphp
                <div wire:key="upcoming-{{ $match->id }}"
                    @class([
                        'flex items-stretch shadow border rounded-lg overflow-hidden transition-opacity',
                        'bg-warning/10 border-warning/40'              => $hasConflict,
                        'bg-base-300 border-base-content/10'           => $isReady && ! $hasConflict,
                        'bg-base-200 border-base-content/5 opacity-50' => ! $isReady,
                    ])>

                    {{-- Position indicator --}}
                    <div @class([
                        'w-12 flex items-center justify-center font-black text-lg shrink-0',
                        'bg-warning text-warning-content'  => $hasConflict,
                        'bg-primary text-primary-content'  => $index === 0 && $isReady && ! $hasConflict,
                        'bg-base-200 text-base-content/40' => ($index > 0 || ! $isReady) && ! $hasConflict,
                    ])>{{ $index + 1 }}</div>

                    <div class="flex-1 p-3">
                        <div class="flex justify-between items-center mb-1">
                            <div class="flex items-center gap-1.5">
                                <x-badge :value="$label"
                                    class="{{ $isPool ? 'badge-ghost' : 'badge-warning' }} badge-xs uppercase font-bold" />
                                @if ($hasConflict)
                                    <span class="flex items-center gap-1 text-[10px] font-bold text-warning-content">
                                        <x-icon name="o-exclamation-triangle" class="w-3 h-3 shrink-0" />
                                        {{ __('Player busy') }}
                                    </span>
                                @endif
                            </div>
                            @if ($index === 0 && $isReady && ! $hasConflict)
                                <x-badge value="{{ __('Next') }}" class="badge-primary badge-xs" />
                            @elseif (! $isReady)
                                <x-badge value="{{ __('Awaiting') }}" class="badge-ghost badge-xs opacity-50" />
                            @endif
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <div @class(['text-sm font-bold flex-1 truncate', 'italic opacity-40' => ! $isReady])>
                                @if ($isReady)
                                    {{ $side1Name }}
                                    <span class="text-[10px] opacity-40 font-normal ml-1">({{ $side1Rank }})</span>
                                @else
                                    {{ __('TBD') }}
                                @endif
                            </div>
                            <div class="text-xs font-black italic opacity-25 shrink-0">VS</div>
                            <div @class(['text-sm font-bold flex-1 truncate text-right', 'italic opacity-40' => ! $isReady])>
                                @if ($isReady)
                                    <span class="text-[10px] opacity-40 font-normal mr-1">({{ $side2Rank }})</span>
                                    {{ $side2Name }}
                                @else
                                    {{ __('TBD') }}
                                @endif
                            </div>
                        </div>
                        @if ($refereeName)
                            <div class="mt-1.5 flex items-center gap-1 text-xs opacity-50">
                                <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                                <span>{{ $refereeName }}</span>
                            </div>
                        @elseif (! $isPool && $isReady && in_array($match->round, ['final', 'bronze']))
                            <div class="mt-1.5 flex items-center gap-1 text-xs opacity-50 italic">
                                <x-icon name="o-eye" class="w-3 h-3 shrink-0" />
                                <span>{{ __('Organisation') }}</span>
                            </div>
                        @elseif (! $isPool && $isReady)
                            <div class="mt-1.5">
                                <x-badge value="{{ __('Referee needed') }}" icon="o-eye" class="badge-ghost badge-xs opacity-50" />
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
