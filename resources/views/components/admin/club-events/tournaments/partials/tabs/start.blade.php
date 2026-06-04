<x-tab name="4" :label="__('Start')" icon="o-rocket-launch">

    @include('components.admin.club-events.tournaments.partials.shared.start-pools', ['isLocked' => false])

    {{-- Launch Section --}}
    <div class="mt-16 flex flex-col items-center text-center animate-in zoom-in-95 duration-500 pb-12">
        <div @class(['w-20 h-20 rounded-full flex items-center justify-center mb-6', 'bg-primary/10 text-primary' => $this->matchesGenerated, 'bg-base-200 text-base-content/30' => !$this->matchesGenerated])>
            <x-icon name="o-check-badge" class="w-12 h-12" />
        </div>
        <h2 class="text-3xl font-black italic uppercase tracking-tight">Ready to go?</h2>
        <p class="max-w-md text-base-content/60 mt-4">
            Configuration is complete. Click below to generate the tournament brackets and notify all participants.
        </p>

        <x-button :label="__('Launch Tournament')" icon="o-play"
            class="btn-primary btn-lg mt-10 shadow-xl shadow-primary/20" wire:click="launch" spinner="launch"
            :disabled="!$this->matchesGenerated || $this->poolsStale" />
    </div>
</x-tab>
