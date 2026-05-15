<div wire:poll.30s class="mt-6">
    @if ($this->pools->isEmpty())
        <div class="flex flex-col items-center py-20 opacity-30">
            <x-icon name="o-user-group" class="w-12 h-12 mb-3" />
            <p class="text-sm">{{ __('No pools generated yet.') }}</p>
        </div>
    @else
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
                        <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 opacity-50 text-xs">
                            <span class="flex-1">{{ __('Player') }}</span>
                            <div class="flex gap-5">
                                <span class="w-8 text-right">{{ __('W') }}</span>
                                <span class="w-8 text-right">{{ __('Sets') }}</span>
                                <span class="w-8 text-right">{{ __('Pts') }}</span>
                            </div>
                        </div>

                        @foreach ($pool['players'] as $i => $entry)
                            @php $user = $entry['player']; @endphp
                            <div wire:key="pool-{{ $pool['id'] }}-player-{{ $user->id ?? $i }}"
                                @class([
                                    'flex justify-between items-center border-b border-base-300/30 py-1.5',
                                    'text-primary font-semibold' => $user->id === auth()->id(),
                                ])>
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <span class="text-xs font-mono opacity-30 w-4 shrink-0">{{ $i + 1 }}</span>
                                    <span class="truncate text-sm font-medium">{{ $user->full_name ?? '—' }}</span>
                                    @if ($user->id === auth()->id())
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
