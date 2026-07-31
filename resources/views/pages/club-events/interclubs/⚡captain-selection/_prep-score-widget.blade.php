{{-- Guarded on the weeks, not on the score: once every match has been played
     the score has nothing left to measure, but the season grid is still worth
     reading. --}}
@if ($isAdminOrCommittee && $weekSummary && $weekSummary['weeks'] !== [])
    {{-- The team zoom only dims rows, so it stays client-side: routing it
         through Livewire made every chip click re-render the whole page. The
         query string is kept by hand so the view still survives a reload. --}}
    <div
        x-data="{
            zoomed: new URLSearchParams(window.location.search).get('zoomedTeamId'),
            select(id) {
                const next = id === null ? null : String(id);
                this.zoomed = this.zoomed === next ? null : next;
                const url = new URL(window.location);
                this.zoomed === null
                    ? url.searchParams.delete('zoomedTeamId')
                    : url.searchParams.set('zoomedTeamId', this.zoomed);
                window.history.replaceState({}, '', url);
            },
            isDimmed(id) { return this.zoomed !== null && this.zoomed !== String(id); },
        }"
        class="mb-6 space-y-3 rounded-xl border border-base-200 bg-base-50 px-4 py-4 sm:px-5">

        {{-- Header: score global --}}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-black uppercase tracking-widest opacity-40">{{ __('Preparation') }}</span>
                @if ($weekSummary['total'] > 0)
                    <span class="text-sm font-bold">{{ $weekSummary['ok'] }}/{{ $weekSummary['total'] }} {{ __('weeks ready') }}</span>
                @else
                    <span class="text-sm font-bold opacity-50">{{ __('Season over') }}</span>
                @endif
            </div>
            {{-- Légende compacte --}}
            <div class="hidden items-center gap-3 text-[9px] font-bold opacity-50 sm:flex">
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-success"></span>{{ __('Confirmed') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-warning"></span>{{ __('Actionable') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-error"></span>{{ __('Urgent') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm bg-base-300"></span>{{ __('Upcoming') }}</span>
                <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm border border-base-300"></span>{{ __('Played') }}</span>
            </div>
        </div>

        {{-- Zoom par équipe --}}
        <div class="flex flex-wrap gap-1.5">
            <button
                type="button"
                x-on:click="select(null)"
                class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold transition-colors"
                x-bind:class="zoomed === null
                    ? 'border-primary bg-primary/10 text-primary'
                    : 'border-base-200 text-base-content/40 hover:border-base-300'"
            >{{ __('All') }}</button>
            @foreach ($weekSummary['teams'] as $t)
                <button
                    type="button"
                    x-on:click="select({{ $t['id'] }})"
                    class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold transition-colors"
                    x-bind:class="zoomed === '{{ $t['id'] }}'
                        ? 'border-primary bg-primary/10 text-primary'
                        : 'border-base-200 text-base-content/40 hover:border-base-300'"
                >{{ $t['name'] }}</button>
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
                        <tr class="transition-opacity duration-150"
                            x-bind:class="isDimmed({{ $t['id'] }}) && 'opacity-20'">
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
                                    @elseif ($cellStatus === 'past')
                                        {{-- Hollow rather than a second shade of grey: two greys are
                                             indistinguishable at this size, an empty square is not. --}}
                                        <span class="inline-block h-2.5 w-2.5 rounded-sm border border-base-300"></span>
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
            <span class="flex items-center gap-1"><span class="inline-block h-2 w-2 rounded-sm border border-base-300"></span>{{ __('Played') }}</span>
        </div>

    </div>
@endif
