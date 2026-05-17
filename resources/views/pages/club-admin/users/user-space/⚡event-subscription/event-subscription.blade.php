<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Tournaments, dinners, and club meetings') }}"
        title="{{ __('Events and Activities') }}" />

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        {{-- ── Sidebar filtres ──────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <x-card class="border border-primary/10 bg-primary/5" shadow title="{{ __('Filters') }}">
                <x-checkbox
                    class="mt-2"
                    label="{{ __('Only upcoming') }}"
                    tight
                    wire:model.live="onlyUpcoming"
                />
            </x-card>

            @php $nextTournament = $this->upcomingTournaments->first(); @endphp
            @if ($nextTournament)
                <x-card class="border border-primary/10 bg-primary/5" shadow>
                    <div class="mb-1 text-[10px] font-bold uppercase tracking-wider opacity-50">
                        {{ __('Next tournament') }}
                    </div>
                    <div class="font-black text-primary">{{ $nextTournament->name }}</div>
                    <div class="mt-0.5 text-xs opacity-70">
                        {{ $nextTournament->start_date->translatedFormat('d M Y') }}
                    </div>
                    @php $reg = $nextTournament->users->first()?->pivot; @endphp
                    @if (! $reg || ! in_array($reg->registration_status, ['registered', 'confirmed', 'spot_offered', 'waiting']))
                        <x-button
                            class="btn-primary btn-xs mt-3"
                            label="{{ __('Quick register') }}"
                            spinner="register"
                            wire:click="register({{ $nextTournament->id }})"
                        />
                    @endif
                </x-card>
            @endif
        </div>

        {{-- ── Contenu principal ────────────────────────────────────────────── --}}
        <div class="space-y-6 lg:col-span-3">

            {{-- Section : À venir --}}
            <x-card icon="o-calendar-days" separator shadow title="{{ __('Upcoming Tournaments') }}">

                @forelse ($this->upcomingTournaments as $tournament)
                    @php
                        $reg          = $tournament->users->first()?->pivot;
                        $regStatus    = $reg?->registration_status;
                        $isActive     = in_array($regStatus, ['registered', 'confirmed', 'spot_offered']);
                        $isWaiting    = $regStatus === 'waiting';
                        $isFull       = $tournament->max_users > 0
                            && $tournament->active_registrations_count >= $tournament->max_users;
                        $remaining    = $tournament->max_users > 0
                            ? max(0, $tournament->max_users - $tournament->active_registrations_count)
                            : null;
                    @endphp

                    <x-admin.shared.compact-event-preview
                        :location="null"
                        :remainingSlots="$remaining"
                        :startDateTime="$tournament->start_date->format('Y-m-d H:i:s')"
                        link="#"
                        name="{{ $tournament->name }}"
                        type="tournament"
                    >
                        <x-slot:actions>

                            {{-- Statut inscription --}}
                            @if ($isActive)
                                <x-badge class="badge-success badge-sm" value="{{ __('Registered') }}" />
                                <x-button
                                    class="btn-ghost btn-sm text-error"
                                    icon="o-x-circle"
                                    label="{{ __('Cancel') }}"
                                    spinner="cancelRegistration"
                                    wire:click="cancelRegistration({{ $tournament->id }})"
                                    wire:confirm="{{ __('Cancel your registration for this tournament?') }}"
                                />
                            @elseif ($isWaiting)
                                <x-badge class="badge-warning badge-sm" value="{{ __('Waitlisted') }}" />
                                <x-button
                                    class="btn-ghost btn-sm text-error"
                                    icon="o-x-circle"
                                    label="{{ __('Leave waitlist') }}"
                                    spinner="cancelRegistration"
                                    wire:click="cancelRegistration({{ $tournament->id }})"
                                    wire:confirm="{{ __('Leave the waitlist for this tournament?') }}"
                                />
                            @elseif ($isFull)
                                <x-badge class="badge-ghost badge-sm" value="{{ __('Full') }}" />
                                <x-button
                                    class="btn-outline btn-sm btn-warning px-4"
                                    label="{{ __('Join waitlist') }}"
                                    spinner="register"
                                    wire:click="register({{ $tournament->id }})"
                                />
                            @else
                                @if ($tournament->price > 0)
                                    <span class="text-xs font-medium text-base-content/60">
                                        {{ number_format((float) $tournament->price, 2, ',', ' ') }} €
                                    </span>
                                @endif
                                <x-button
                                    class="btn-primary btn-outline btn-sm px-6"
                                    label="{{ __('Register') }}"
                                    spinner="register"
                                    wire:click="register({{ $tournament->id }})"
                                />
                            @endif

                        </x-slot:actions>
                    </x-admin.shared.compact-event-preview>

                @empty
                    <div class="flex flex-col items-center py-10 text-base-content/40">
                        <x-icon class="mb-3 h-10 w-10" name="o-calendar" />
                        <p class="text-sm">{{ __('No upcoming tournaments at the moment.') }}</p>
                    </div>
                @endforelse

            </x-card>

            {{-- Section : Passés (collapse) --}}
            @if ($this->myPastTournaments->isNotEmpty())
                <x-collapse>
                    <x-slot:heading>
                        <div class="text-sm font-bold opacity-40">
                            {{ __('Past tournaments') }}
                            <span class="ml-1 font-normal">({{ $this->myPastTournaments->count() }})</span>
                        </div>
                    </x-slot:heading>
                    <x-slot:content>
                        <div class="space-y-1 opacity-60">
                            @foreach ($this->myPastTournaments as $tournament)
                                <div class="flex items-center justify-between border-b border-dashed py-2 text-sm">
                                    <span class="font-medium">{{ $tournament->name }}</span>
                                    <span class="text-xs text-base-content/60">
                                        {{ $tournament->start_date->translatedFormat('d M Y') }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </x-slot:content>
                </x-collapse>
            @endif

        </div>
    </div>
</div>
