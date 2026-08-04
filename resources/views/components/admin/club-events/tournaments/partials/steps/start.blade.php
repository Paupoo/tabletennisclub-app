@include('components.admin.club-events.tournaments.partials.shared.start-pools', ['isLocked' => $this->isLaunched])

{{-- Launch Section --}}
<div class="mt-16 flex flex-col items-center text-center animate-in zoom-in-95 duration-500 pb-12">
    @if ($this->isLaunched)
        <div class="w-20 h-20 rounded-full flex items-center justify-center mb-6 bg-success/10 text-success">
            <x-icon name="o-rocket-launch" class="w-12 h-12" />
        </div>
        <h2 class="text-3xl font-black italic uppercase tracking-tight text-success">{{ __('Tournament launched!') }}</h2>
        <p class="max-w-md text-base-content/60 mt-4">
            {{ __('The tournament is underway. No further modifications are allowed.') }}
        </p>
        <x-button :label="__('Go to Live Center')" icon="o-arrow-right"
            class="btn-success btn-lg mt-10 shadow-xl shadow-success/20"
            :link="route('admin.tournaments.live-center', $tournamentId)" />
    @else
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
    @endif
</div>

{{-- Require close registrations modal --}}
<x-app-modal wire:model="showRequireCloseRegistrationsModal" :title="__('Registrations are still open')" class="backdrop-blur">
    <div class="space-y-4">
        <div class="flex items-start gap-3 p-4 bg-warning/10 border border-warning/20 rounded-xl text-sm">
            <x-icon name="o-exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5 text-warning-content" />
            <div class="space-y-1">
                <p class="font-semibold">{{ __('You must close registrations before launching.') }}</p>
                <p class="text-base-content/70">{{ __('Closing registrations will remove any players still on the waiting list. This cannot be undone.') }}</p>
            </div>
        </div>
        @php $waitlistCount = $this->waitlist->count(); @endphp
        @if ($waitlistCount > 0)
            <p class="text-sm text-base-content/70">
                {{ trans_choice('1 person on the waiting list will be removed.|:count people on the waiting list will be removed.', $waitlistCount, ['count' => $waitlistCount]) }}
            </p>
        @endif
    </div>
    <x-slot:actions>
        <x-button :label="__('Cancel')" wire:click="$set('showRequireCloseRegistrationsModal', false)" />
        <x-button :label="__('Close registrations & launch')" icon="o-rocket-launch" class="btn-warning"
            wire:click="confirmCloseAndLaunch" spinner="confirmCloseAndLaunch" />
    </x-slot:actions>
</x-app-modal>
