{{--
    Une rencontre du tableau. Extraite pour que l'arbre connecté (desktop) et la
    pile par tour (écran étroit) affichent exactement la même carte.
--}}
@php
    $isDoubles = $match->pair1_id !== null;
    $p1Won = $match->winner_id !== null && $match->winner_id === $match->player1_id;
    $p2Won = $match->winner_id !== null && $match->winner_id === $match->player2_id;
    $side1Name = $isDoubles ? ($match->pair1?->displayName() ?? '—') : ($match->player1?->full_name ?? '—');
    $side2Name = $isDoubles ? ($match->pair2?->displayName() ?? '—') : ($match->player2?->full_name ?? '—');
    $isFinal = ($round ?? null) === 'final';
    $isBronze = ($round ?? null) === 'bronze';
@endphp

<div wire:key="bracket-{{ $match->id }}" @class([
    'space-y-1.5 rounded-xl border p-3',
    'border-2 border-amber-500 bg-base-100' => $isFinal,
    'border-2 border-info bg-base-100' => $isBronze,
    'border-primary/40 bg-base-100' => ! $isFinal && ! $isBronze && $match->status === 'in_progress',
    'border-base-300 bg-base-100' => ! $isFinal && ! $isBronze && $match->status !== 'in_progress',
])>
    <div @class([
        'flex items-center justify-between gap-2 text-sm',
        'font-bold text-success' => $p1Won,
        'text-muted line-through' => $p2Won,
    ])>
        <span class="truncate">{{ $side1Name }}</span>
        <span class="shrink-0 font-mono">{{ $match->getSetsWon($match->player1_id ?? 0) }}</span>
    </div>

    <div class="border-t border-base-300/50"></div>

    <div @class([
        'flex items-center justify-between gap-2 text-sm',
        'font-bold text-success' => $p2Won,
        'text-muted line-through' => $p1Won,
    ])>
        <span class="truncate">{{ $side2Name }}</span>
        <span class="shrink-0 font-mono">{{ $match->getSetsWon($match->player2_id ?? 0) }}</span>
    </div>

    @if ($match->referee)
        <div class="flex items-center gap-1 border-t border-base-300/40 pt-1 text-xs text-muted">
            <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
            <span class="truncate">{{ $match->referee->full_name }}</span>
        </div>
    @elseif ($match->player1_id)
        <div class="flex items-center gap-1 border-t border-base-300/40 pt-1 text-xs italic text-muted">
            <x-icon name="o-eye" class="h-3 w-3 shrink-0" />
            <span>{{ $isFinal || $isBronze ? __('Organisation') : __('Referee needed') }}</span>
        </div>
    @endif

    @if ($match->status === 'completed' && $match->winner_id !== null)
        @php
            $winnerName = $isDoubles
                ? ($p1Won ? $match->pair1?->displayName() : $match->pair2?->displayName())
                : $match->winner?->full_name;
        @endphp
        <div class="pt-1 text-center text-xs font-bold text-amber-500">
            <x-icon name="o-trophy" class="mb-0.5 inline h-3.5 w-3.5" /> {{ $winnerName }}
        </div>
    @endif
</div>
