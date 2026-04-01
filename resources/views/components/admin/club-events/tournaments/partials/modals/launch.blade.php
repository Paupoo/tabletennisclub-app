<x-modal wire:model="showLaunchModal" title="{{ __('Finalizing Tournament') }}" separator persistent>
    <div class="text-center py-10">
        {{-- Une animation plus sympa --}}
        <div class="flex justify-center mb-6">
            <span class="loading loading-dots loading-lg text-primary"></span>
        </div>

        <h3 class="text-xl font-black italic uppercase tracking-widest animate-pulse">
            {{ __('Generating Brackets...') }}
        </h3>

        <p class="text-sm opacity-60 mt-4 max-w-xs mx-auto">
            {{ __('Almost there! We are preparing the matches and notifying the players.') }}
        </p>
    </div>
</x-modal>
