<div wire:poll.10s class="mt-6">

    @if ($this->upcomingMatches->isEmpty())
        <div class="flex flex-col items-center py-20 opacity-30">
            <x-icon name="o-check-circle" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('All matches are done or in progress.') }}</p>
        </div>
    @else
        <div class="flex flex-col gap-3 lg:max-w-2xl">
            @foreach ($this->upcomingMatches as $index => $match)
                @php
                    $isPool   = $match->pool_id !== null;
                    $label    = $isPool ? ($match->pool?->name ?? __('Pool')) : __('Bracket');
                @endphp
                <div wire:key="upcoming-{{ $match->id }}"
                    class="flex items-stretch bg-base-300 shadow border border-base-content/10 rounded-lg overflow-hidden">

                    {{-- Position indicator --}}
                    <div @class([
                        'w-12 flex items-center justify-center font-black text-lg shrink-0',
                        'bg-primary text-primary-content' => $index === 0,
                        'bg-base-200 text-base-content/40' => $index > 0,
                    ])>{{ $index + 1 }}</div>

                    <div class="flex-1 p-3">
                        <div class="flex justify-between items-center mb-1">
                            <x-badge :value="$label"
                                class="{{ $isPool ? 'badge-ghost' : 'badge-warning' }} badge-xs uppercase font-bold" />
                            @if ($index === 0)
                                <x-badge value="{{ __('Next') }}" class="badge-primary badge-xs" />
                            @endif
                        </div>
                        <div class="flex justify-between items-center gap-2">
                            <div class="text-sm font-bold flex-1 truncate">
                                {{ $match->player1?->full_name ?? '—' }}
                                <span class="text-[10px] opacity-40 font-normal ml-1">({{ $match->player1?->ranking ?? 'NC' }})</span>
                            </div>
                            <div class="text-xs font-black italic opacity-25 shrink-0">VS</div>
                            <div class="text-sm font-bold flex-1 truncate text-right">
                                <span class="text-[10px] opacity-40 font-normal mr-1">({{ $match->player2?->ranking ?? 'NC' }})</span>
                                {{ $match->player2?->full_name ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-12 p-4 bg-primary/10 border border-primary/20 rounded-lg flex items-center gap-4 lg:max-w-2xl">
            <x-icon name="o-information-circle" class="w-6 h-6 text-primary shrink-0" />
            <p class="text-xs leading-tight text-base-content/70">
                {{ __('Matches are refereed by the players from the previous match on the same table.') }}
            </p>
        </div>
    @endif

</div>
