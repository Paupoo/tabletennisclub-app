<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator subtitle="{{ __('Upcoming club activities') }}" title="{{ __('Calendar') }}">
        <x-slot:actions>
            <x-button class="btn-outline btn-sm" icon="o-arrow-path" label="{{ __('Sync to Google/iCal') }}" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        <div class="space-y-4">
            <x-card class="border border-primary/20 bg-primary/5" shadow title="{{ __('Filters') }}">
                <div class="space-y-4">
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">{{ __('Category') }}</span>
                        </label>
                        <x-choices
                            :options="collect($categories)"
                            placeholder="{{ __('All categories') }}"
                            single
                            wire:model.live="selectedCategory"
                        />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="space-y-8 lg:col-span-3">
            @forelse ($calendar as $month => $events)
                <x-card :title="$month" class="mb-8" shadow>
                    @foreach ($events as $event)
                        @php
                            $regStatus = $event['registrationStatus'] ?? null;
                            $isActive  = in_array($regStatus, ['registered', 'confirmed', 'spot_offered']);
                            $isWaiting = $regStatus === 'waiting';
                            $isTraining = $event['type'] === 'training';
                        @endphp
                        <x-admin.shared.compact-event-preview
                            :name="$event['title']"
                            :startDateTime="$event['startDateTime']"
                            :type="$event['type']"
                            link="#"
                            :location="$event['room'] ?? ''"
                        >
                            <x-slot:actions>
                                @if ($isTraining)
                                    @if (isset($event['level']))
                                        <x-badge class="badge-primary badge-soft badge-sm" value="{{ $event['level'] }}" />
                                    @endif
                                    <x-badge class="badge-success badge-sm" value="{{ __('Enrolled') }}" />
                                @elseif ($isActive)
                                    <x-badge class="badge-success badge-sm" value="{{ __('Registered') }}" />
                                @elseif ($isWaiting)
                                    <x-badge class="badge-warning badge-sm" value="{{ __('Waitlisted') }}" />
                                @else
                                    <a class="btn btn-primary btn-outline btn-xs"
                                        href="{{ route('admin.user.event-subscription', $user) }}">
                                        {{ __('Register') }}
                                    </a>
                                @endif
                                <x-icon class="h-5 w-5 opacity-20 transition-opacity group-hover:opacity-100"
                                    name="o-chevron-right" />
                            </x-slot:actions>
                        </x-admin.shared.compact-event-preview>
                    @endforeach
                </x-card>
            @empty
                <div class="flex flex-col items-center py-16 text-base-content/40">
                    <x-icon class="mb-3 h-10 w-10" name="o-calendar" />
                    <p class="text-sm">{{ __('No upcoming tournaments.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
