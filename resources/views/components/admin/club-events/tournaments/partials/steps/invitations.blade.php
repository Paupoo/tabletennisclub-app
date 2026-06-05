<div class="mt-8 animate-in fade-in duration-500">

    @if ($this->isLaunched)
        <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-base-200 border border-base-300 text-sm">
            <x-icon name="o-lock-closed" class="w-5 h-5 shrink-0 text-base-content/40" />
            <p class="text-base-content/60">{{ __('The tournament has been launched. Invitations are read-only.') }}</p>
        </div>
    @endif

    {{-- Registration deadline reminder --}}
    @if ($registration_deadline)
        <div class="mb-6 flex items-center gap-3 p-3 rounded-xl bg-info/5 border border-info/20 text-sm">
            <x-icon name="o-calendar-days" class="w-5 h-5 text-info shrink-0" />
            <span class="text-base-content/70">{{ __('Registration deadline:') }}</span>
            <span class="font-semibold">{{ \Carbon\Carbon::parse($registration_deadline)->format('d/m/Y') }}</span>
        </div>
    @else
        <x-alert
            :title="__('Registration deadline not set')"
            :description="__('Please set a registration deadline in step 1 before sending invitations.')"
            icon="o-exclamation-triangle"
            class="alert-warning alert-soft mb-6" />
    @endif

    @include('components.admin.club-events.tournaments.partials.shared.invitations-content', ['isLocked' => $this->isLaunched])

</div>
