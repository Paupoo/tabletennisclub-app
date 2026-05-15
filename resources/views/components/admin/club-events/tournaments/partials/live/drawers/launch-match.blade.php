<x-drawer wire:model="launchDrawer" title="{{ __('Launch a match') }}" right separator with-close-button class="lg:w-1/3">

    <div class="space-y-4">
        <p class="text-sm text-base-content/60">
            {{ __('Select a match to assign to this table. The recommended match is highlighted.') }}
        </p>

        @if ($this->upcomingMatches->isEmpty())
            <div class="flex flex-col items-center py-16 opacity-30">
                <x-icon name="o-no-symbol" class="w-12 h-12 mx-auto mb-3" />
                <p class="text-sm">{{ __('No matches scheduled.') }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($this->upcomingMatches as $index => $match)
                    @php
                        $isFirst = $index === 0;
                        $isPool  = $match->pool_id !== null;
                        $label   = $isPool ? ($match->pool?->name ?? __('Pool')) : __('Bracket');
                    @endphp

                    <div class="relative group" wire:key="launch-match-{{ $match->id }}">
                        @if ($isFirst)
                            <div class="absolute -top-2 left-4 z-10">
                                <x-badge value="{{ __('Recommended') }}" class="badge-primary badge-xs font-bold shadow-sm" />
                            </div>
                        @endif

                        <div wire:click="startMatch({{ $match->id }})"
                            class="p-4 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between
                            {{ $isFirst ? 'border-primary bg-primary/5 ring-1 ring-primary/20' : 'border-base-200 hover:border-primary/40 bg-base-100' }}">

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-badge :value="$label"
                                        class="{{ $isPool ? 'badge-ghost' : 'badge-warning' }} badge-xs uppercase font-bold" />
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-sm truncate">{{ $match->player1?->full_name ?? '—' }}</span>
                                    <span class="text-[10px] opacity-30 italic font-black my-0.5">VS</span>
                                    <span class="font-bold text-sm truncate">{{ $match->player2?->full_name ?? '—' }}</span>
                                </div>
                            </div>

                            <div class="ml-4 shrink-0">
                                <x-button icon="o-play"
                                    class="btn-circle {{ $isFirst ? 'btn-primary' : 'btn-ghost' }} btn-sm"
                                    wire:loading.attr="disabled" />
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <x-slot:actions>
        <x-button label="{{ __('Cancel') }}" @click="$wire.launchDrawer = false" />
    </x-slot:actions>

</x-drawer>
