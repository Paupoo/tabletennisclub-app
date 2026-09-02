<div class="mt-8 animate-in fade-in duration-500">

    @if ($this->isLaunched)
        <div class="mb-6 flex items-center gap-3 p-4 rounded-xl bg-base-200 border border-base-300 text-sm">
            <x-icon name="o-lock-closed" class="w-5 h-5 shrink-0 text-base-content/40" />
            <p class="text-base-content/60">{{ __('The tournament has been launched. Invitations are read-only.') }}</p>
        </div>
    @endif

    {{-- ── Ouverture des inscriptions ────────────────────────────────────
         Le geste que le comité cherchait sous le nom « Publier ». Tant qu'il
         n'est pas posé, le tournoi n'existe pas pour les membres : il ne sort
         ni dans « Mes inscriptions », ni dans une invitation utile (issue #35). --}}
    @if ($this->canOpenRegistrations && ! $this->isLaunched)
        <div class="mb-6 rounded-xl border border-warning/40 bg-warning/5 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <x-icon name="o-lock-closed" class="mt-0.5 h-5 w-5 shrink-0 text-warning" />
                    <div>
                        <p class="font-semibold">{{ __('Registrations are closed') }}</p>
                        <p class="text-sm text-base-content/70">
                            {{ __('Members cannot see this tournament yet, and invitations would lead them nowhere.') }}
                        </p>
                    </div>
                </div>
                <x-button
                    :label="__('Open registrations')"
                    icon="o-lock-open"
                    class="btn-primary shrink-0"
                    wire:click="$set('showOpenRegistrationsModal', true)"
                    spinner />
            </div>
        </div>
    @elseif ($this->registrationsOpen)
        <div class="mb-6 flex items-center gap-3 rounded-xl border border-success/30 bg-success/5 p-3 text-sm">
            <x-icon name="o-lock-open" class="h-5 w-5 shrink-0 text-success" />
            <span>{{ __('Registrations are open — members can sign up.') }}</span>
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
