@if ($isAdminOrCommittee && $weekSummary && $weekSummary['total'] > 0)
    <div class="mb-6 space-y-3 rounded-xl border border-base-200 bg-base-50 px-4 py-4 sm:px-5">

        {{-- Header: score global --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-black uppercase tracking-widest opacity-40">{{ __('Preparation') }}</span>
                <span class="text-sm font-bold">{{ $weekSummary['ok'] }}/{{ $weekSummary['total'] }} {{ __('weeks ready') }}</span>
            </div>
            {{-- Légende compacte --}}
            <div class="hidden items-center gap-3 text-[9px] font-bold opacity-50 sm:flex">
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-success"></span>{{ __('Confirmed') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-warning"></span>{{ __('Actionable') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-error"></span>{{ __('Urgent') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-base-300"></span>{{ __('Upcoming') }}</span>
            </div>
        </div>

        {{-- Zoom par équipe --}}
        <div class="flex flex-wrap gap-1.5">
            <button
                wire:click="$set('zoomedTeamId', null)"
                @class([
                    'rounded-full border px-2.5 py-0.5 text-[10px] font-bold transition-colors',
                    'border-primary bg-primary/10 text-primary' => $zoomedTeamId === null,
                    'border-base-200 text-base-content/40 hover:border-base-300' => $zoomedTeamId !== null,
                ])>{{ __('All') }}</button>
            @foreach ($weekSummary['teams'] as $t)
                <button
                    wire:click="$set('zoomedTeamId', {{ $t['id'] }})"
                    @class([
                        'rounded-full border px-2.5 py-0.5 text-[10px] font-bold transition-colors',
                        'border-primary bg-primary/10 text-primary' => $zoomedTeamId === $t['id'],
                        'border-base-200 text-base-content/40 hover:border-base-300' => $zoomedTeamId !== $t['id'],
                    ])>{{ $t['name'] }}</button>
            @endforeach
        </div>

        {{-- Grille équipes × semaines --}}
        <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
            <table class="w-full border-separate border-spacing-0 text-[10px]">
                <thead>
                    <tr>
                        {{-- Colonne sticky équipe --}}
                        <th class="sticky left-0 z-10 bg-base-50 pb-1 pr-3 text-left font-normal opacity-0 select-none">
                            ████████
                        </th>
                        @foreach ($weekSummary['weeks'] as $wk)
                            <th class="px-1 pb-1 text-center font-black opacity-30">
                                S{{ $matchDayMap[$wk['wk']] ?? $wk['wk'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weekSummary['teams'] as $t)
                        @php $isZoomed = $zoomedTeamId && $zoomedTeamId !== $t['id']; @endphp
                        <tr @class(['transition-opacity duration-150', 'opacity-20' => $isZoomed])>
                            {{-- Nom équipe sticky --}}
                            <td class="sticky left-0 z-10 bg-base-50 py-1 pr-3 font-bold whitespace-nowrap text-base-content/60">
                                {{ $t['name'] }}
                            </td>
                            @foreach ($weekSummary['weeks'] as $wk)
                                @php $cellStatus = $weekSummary['matrix'][$t['id']][$wk['wk']] ?? null; @endphp
                                <td class="px-1 py-1 text-center">
                                    @if ($cellStatus === null)
                                        <span class="text-base-content/15 leading-none">·</span>
                                    @elseif ($cellStatus === 'confirmed')
                                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-success"></span>
                                    @elseif ($cellStatus === 'actionable')
                                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-warning"></span>
                                    @elseif ($cellStatus === 'urgent')
                                        <span class="inline-block h-2.5 w-2.5 animate-pulse rounded-sm bg-error"></span>
                                    @else
                                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-base-300"></span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Légende mobile --}}
        <div class="flex items-center gap-3 text-[9px] font-bold opacity-40 sm:hidden">
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-success"></span>{{ __('Confirmed') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-warning"></span>{{ __('Actionable') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-error"></span>{{ __('Urgent') }}</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-base-300"></span>{{ __('Upcoming') }}</span>
        </div>

    </div>
@endif
