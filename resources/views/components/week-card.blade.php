@props(['week', 'opponent', 'date', 'score' => null, 'matches' => null, 'selectionCount' => 0])

<div @if ($isExpandable()) x-data="{ open: false }"
        @click="open = !open" @endif
    @class([
        'flex items-center justify-between px-4 py-3 gap-3 bg-base-100 transition-colors',
        'cursor-pointer hover:bg-base-200/40' => $isExpandable(),
        'opacity-40' => $status === 'future',
    ])>
    <div class="flex min-w-0 flex-1 items-center gap-3">

        {{-- Barre verticale indicateur --}}
        <div @class([
            'w-1 h-8 rounded-full flex-shrink-0',
            $barColor(),
            $barOpacity(),
        ])></div>

        {{-- Numéro de semaine --}}
        <div class="min-w-[32px] text-center">
            <div class="text-[9px] font-medium uppercase opacity-35">WK</div>
            <div class="text-lg font-medium leading-none">{{ $week }}</div>
        </div>

        {{-- Infos match --}}
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span class="truncate text-sm font-medium">vs {{ $opponent }}</span>
                @if ($status === 'pending')
                    <x-badge class="badge-warning badge-xs px-2 font-medium" value="Action requise" />
                @endif
            </div>
            <div class="text-base-content/50 mt-0.5 text-[11px]">{{ $date }}</div>
        </div>
    </div>

    {{-- Partie droite --}}
    <div class="flex flex-shrink-0 items-center gap-3">

        {{-- Score coloré (résultats passés) --}}
        @if ($score !== null)
            <div class="flex items-center gap-1">
                <div @class([
                    'text-sm font-medium font-mono rounded px-1.5 py-0.5',
                    $scoreHomeClass(),
                ])>{{ $score['home'] }}</div>
                <span class="text-base-content/30 text-xs">–</span>
                <div class="bg-base-200 rounded px-1.5 py-0.5 font-mono text-sm font-medium">
                    {{ $score['away'] }}
                </div>
            </div>
        @endif

        {{-- Dots sélection (planning) --}}
        @if (in_array($status, ['pending', 'ready', 'future']))
            <div class="flex flex-col items-end gap-1">
                <div class="text-[9px] font-medium uppercase tracking-wide opacity-40">Sélection</div>
                <div class="flex gap-1">
                    @for ($i = 1; $i <= 4; $i++)
                        <div @class([
                            'w-2 h-2 rounded-full transition-all duration-500',
                            'bg-success shadow-[0_0_5px_rgba(34,197,94,0.4)]' => $i <= $selectionCount,
                            'bg-base-300' => $i > $selectionCount,
                        ])></div>
                    @endfor
                </div>
            </div>
        @endif

        {{-- Pastille statut --}}
        <div @class(['w-2 h-2 rounded-full flex-shrink-0', $dotStyle()])></div>

        {{-- Actions selon le status --}}
        @if ($status === 'pending')
            <x-button @click.stop="$wire.set('drawerSelection', true)"
                class="btn-primary btn-sm rounded-xl text-xs font-medium" icon="o-pencil-square" label="Select" />
        @elseif($status === 'ready')
            <x-button @click.stop="$wire.set('drawerSelection', true)"
                class="btn-ghost border-base-300 btn-sm rounded-xl text-xs font-medium" icon="o-pencil-square"
                label="Edit" />
        @elseif($isExpandable())
            <x-icon class="h-3.5 w-3.5 opacity-30 transition-transform duration-200" name="o-chevron-down"
                x-bind:class="open ? 'rotate-180' : ''" />
        @endif
    </div>
</div>

{{-- Zone expandable — matchs individuels --}}
@if ($isExpandable())
    <div class="border-base-200 bg-base-50 border-t px-4 py-3" x-collapse x-show="open">
        <div class="grid grid-cols-1 gap-1.5 md:grid-cols-2">
            @foreach ($matches as $match)
                <div @class([
                    'flex items-center justify-between p-2.5 rounded-lg border text-xs',
                    'border-success/20' => $match['win'],
                    'border-error/20' => !$match['win'],
                ])>
                    <div class="flex items-center gap-2">
                        <div @class([
                            'w-1.5 h-1.5 rounded-full',
                            'bg-success' => $match['win'],
                            'bg-error' => !$match['win'],
                        ])></div>
                        <div>
                            <div class="font-medium">{{ $match['opp_name'] }}</div>
                            @isset($match['opp_rank'])
                                <div class="text-[10px] uppercase tracking-tight opacity-50">{{ $match['opp_rank'] }}</div>
                            @endisset
                        </div>
                    </div>
                    <span @class([
                        'font-mono font-medium',
                        'text-success' => $match['win'],
                        'text-error' => !$match['win'],
                    ])>{{ $match['sets'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
