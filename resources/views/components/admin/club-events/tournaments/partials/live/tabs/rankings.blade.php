@php
    $rankings  = $this->rankings;
    $isDoubles = $tournament->match_type === 'double';
    $top3      = $rankings->take(3)->keyBy('rank');
    $rest      = $rankings->skip(3);

    $podiumOrder    = [2, 1, 3];
    $podiumHeight   = [2 => 'h-16', 1 => 'h-24', 3 => 'h-10'];
    $podiumLabel    = [1 => __('Champion'), 2 => __('Runner-up'), 3 => __('3rd place')];
    $podiumRing     = [
        1 => 'ring-2 ring-amber-400 ring-offset-2 ring-offset-base-100',
        2 => 'ring-2 ring-slate-400 ring-offset-2 ring-offset-base-100',
        3 => 'ring-2 ring-orange-400 ring-offset-2 ring-offset-base-100',
    ];
    $podiumPlatform = [
        1 => 'bg-amber-400/20 border-t-2 border-amber-400/40',
        2 => 'bg-slate-400/10 border-t-2 border-slate-400/30',
        3 => 'bg-orange-400/10 border-t-2 border-orange-400/30',
    ];
    $podiumNumber   = [
        1 => 'text-amber-400',
        2 => 'text-slate-400',
        3 => 'text-orange-400',
    ];

    $entryName = function (array $entry) use ($isDoubles): string {
        if ($isDoubles && isset($entry['pair'])) {
            return $entry['pair']->displayName();
        }
        return $entry['user']->full_name ?? '—';
    };

    $entryInitials = function (array $entry) use ($isDoubles): string {
        if ($isDoubles && isset($entry['pair'])) {
            $p1 = mb_strtoupper(mb_substr($entry['pair']->player1->last_name ?? '?', 0, 1));
            $p2 = mb_strtoupper(mb_substr($entry['pair']->player2->last_name ?? '?', 0, 1));
            return "{$p1}/{$p2}";
        }
        $f = mb_strtoupper(mb_substr($entry['user']->first_name ?? '?', 0, 1));
        $l = mb_strtoupper(mb_substr($entry['user']->last_name ?? '', 0, 1));
        return "{$f}{$l}";
    };
@endphp

<div @if($tournament->status === \App\Domains\Shared\Enums\TournamentStatusEnum::PENDING) wire:poll.5s @endif class="mt-6">

    @if ($rankings->isEmpty())
        <div class="flex flex-col items-center py-20 text-muted">
            <x-icon name="o-chart-bar" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('Rankings will appear as matches are completed.') }}</p>
        </div>

    @else

        {{-- ── Podium (top 3) ────────────────────────────────────────── --}}
        @if ($top3->count() >= 2)
            <div class="flex items-end justify-center gap-4 mb-8 px-4">
                @foreach ($podiumOrder as $rank)
                    @if ($entry = $top3->get($rank))
                        <div class="flex flex-col items-center flex-1 min-w-0">

                            {{-- Rank number --}}
                            <span @class(['text-3xl font-black text-muted leading-none mb-1', $podiumNumber[$rank]])>
                                {{ $rank }}
                            </span>

                            {{-- Avatar --}}
                            <div @class(['w-12 h-12 rounded-full flex items-center justify-center bg-base-200 font-black text-sm mb-2', $podiumRing[$rank]])>
                                {{ $entryInitials($entry) }}
                            </div>

                            {{-- Name --}}
                            <p class="text-xs font-bold text-center leading-tight truncate w-full">
                                {{ $entryName($entry) }}
                            </p>

                            {{-- Platform --}}
                            <div @class(['w-full rounded-t-md mt-2 flex items-center justify-center', $podiumHeight[$rank], $podiumPlatform[$rank]])>
                                <span class="text-xs font-bold uppercase tracking-wider text-muted">
                                    {{ $podiumLabel[$rank] }}
                                </span>
                            </div>

                        </div>
                    @endif
                @endforeach
            </div>
        @endif

        {{-- ── Full list ─────────────────────────────────────────────── --}}
        <div class="space-y-1">
            @foreach ($rankings as $entry)
                @php $rank = $entry['rank']; @endphp
                <div wire:key="ranking-{{ $isDoubles && isset($entry['pair']) ? 'pair-'.$entry['pair']->id : ($entry['user']->id ?? $rank) }}"
                    @class([
                        'flex items-center gap-3 px-3 py-2.5 rounded-lg',
                        'bg-amber-400/10'  => $rank === 1,
                        'bg-slate-400/10'  => $rank === 2,
                        'bg-orange-400/10' => $rank === 3,
                        'hover:bg-base-200/50 transition-colors' => $rank > 3,
                    ])>

                    {{-- Rank --}}
                    <span @class([
                        'w-6 text-center text-xs font-mono font-black shrink-0',
                        'text-amber-500'  => $rank === 1,
                        'text-slate-400'  => $rank === 2,
                        'text-orange-400' => $rank === 3,
                        'text-muted'      => $rank > 3,
                    ])>{{ $rank }}</span>

                    {{-- Avatar --}}
                    <div @class([
                        'w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-black shrink-0 bg-base-200',
                        'ring-1 ring-amber-400'  => $rank === 1,
                        'ring-1 ring-slate-400'  => $rank === 2,
                        'ring-1 ring-orange-400' => $rank === 3,
                    ])>
                        {{ $entryInitials($entry) }}
                    </div>

                    {{-- Name --}}
                    <span @class([
                        'flex-1 text-sm font-semibold truncate',
                        'font-bold' => $rank <= 3,
                    ])>{{ $entryName($entry) }}</span>

                    {{-- Result label --}}
                    <span class="text-xs text-muted shrink-0">{{ $entry['result'] }}</span>

                </div>
            @endforeach
        </div>

    @endif

</div>
