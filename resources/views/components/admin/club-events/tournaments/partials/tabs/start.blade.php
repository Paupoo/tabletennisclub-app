<x-tab name="4" label="{{ __('Start') }}" icon="o-rocket-launch">

    {{-- Pools Section (Original Style) --}}
    <x-header title="{{ __('Pools ranking') }}" subtitle="Rearrange players if needed" class="mt-8" size="md">
        <x-slot:actions>
            <x-button label="{{ __('Final setup') }}" class="btn-primary btn-sm" @click="$wire.setupDrawer = true" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($pools as $poolId => $players)
        {{-- On ajoute wire:key sur la card pour Livewire --}}
        <x-card wire:key="pool-card-{{ $poolId }}" title="Pool {{ $poolId }}" shadow compact
            class="border-0">
            <div>
                {{-- Header --}}
                <div class="flex justify-between font-bold border-b border-base-300 pb-1 mb-1 opacity-50 text-sm">
                    <span>{{ __('Player') }}</span>
                    <div class="flex gap-4">
                        <span class="w-10 text-right">{{ __('Rank.') }}</span>
                        <span class="w-6 text-right">{{ __('Pts') }}</span>
                    </div>
                </div>

                {{-- Zone Draggable : on utilise TA fonction initSortable --}}
                <div x-init="initSortable($el, $wire)" data-team-id="{{ $poolId }}" class="min-h-[100px] space-y-1">
                    @foreach ($players as $player)
                    {{-- data-id est crucial pour ta fonction updateStructure dans le controller --}}
                    <div wire:key="player-{{ $player['id'] }}" data-id="{{ $player['id'] }}"
                        @class([ 'flex justify-between items-center border-b border-base-300/30 py-1 group cursor-grab active:cursor-grabbing' , 'text-primary underline underline-offset-4 decoration-2'=>
                        $player['name'] === $loggedPlayer,
                        ])>
                        <div class="flex items-center gap-2 truncate">
                            {{-- L'icône sert de repère visuel mais c'est toute la ligne qui est draggable ici --}}
                            <x-icon name="o-bars-3"
                                class="w-4 h-4 opacity-20 group-hover:opacity-100 transition-opacity" />
                            <span class="truncate font-medium">{{ $player['name'] }}</span>
                        </div>

                        <div class="flex gap-5 items-center">
                            <span
                                class="opacity-70 font-mono w-10 text-right text-xs font-bold">{{ $player['rank'] }}</span>
                            <span class="font-bold w-6 text-right">{{ $player['pts'] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </x-card>
        @endforeach
    </div>

    {{-- Launch Section --}}
    <div class="mt-16 flex flex-col items-center text-center animate-in zoom-in-95 duration-500 pb-12">
        <div class="w-20 h-20 bg-primary/10 text-primary rounded-full flex items-center justify-center mb-6">
            <x-icon name="o-check-badge" class="w-12 h-12" />
        </div>
        <h2 class="text-3xl font-black italic uppercase tracking-tight">Ready to go?</h2>
        <p class="max-w-md text-base-content/60 mt-4">
            Configuration is complete. Click below to generate the tournament brackets and notify all
            participants.
        </p>

        <x-button label="{{ __('Launch Tournament') }}" icon="o-play"
            class="btn-primary btn-lg mt-10 shadow-xl shadow-primary/20" wire:click="launch" spinner="launch"
            :disabled="!$registrationClosed" />
    </div>
</x-tab>