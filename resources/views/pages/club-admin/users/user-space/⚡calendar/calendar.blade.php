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

                    {{-- Toggle vue personnelle / tous les événements du club --}}
                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">{{ __('View') }}</span>
                        </label>
                        <div class="flex gap-1 rounded-xl bg-base-200 p-1">
                            <button
                                wire:click="$set('showAllEvents', false)"
                                @class(['flex-1 rounded-lg py-1.5 text-center text-xs font-semibold transition-all',
                                    'bg-base-100 shadow' => !$showAllEvents,
                                    'opacity-50 hover:opacity-75' => $showAllEvents])>
                                {{ __('My events') }}
                            </button>
                            <button
                                wire:click="$set('showAllEvents', true)"
                                @class(['flex-1 rounded-lg py-1.5 text-center text-xs font-semibold transition-all',
                                    'bg-base-100 shadow' => $showAllEvents,
                                    'opacity-50 hover:opacity-75' => !$showAllEvents])>
                                {{ __('All events') }}
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="label">
                            <span class="label-text font-semibold">{{ __('Category') }}</span>
                        </label>
                        <x-choices
                            :options="collect($categories)"
                            placeholder="{{ __('All categories') }}"
                            wire:model.live="selectedCategories"
                        />
                    </div>
                </div>
            </x-card>
        </div>

        <div class="lg:col-span-3">
            @php
                $typeColors = [
                    'training'   => 'bg-accent',
                    'tournament' => 'bg-secondary',
                    'interclub'  => 'bg-primary',
                ];
                $typeBadgeClasses = [
                    'training'   => 'badge-accent badge-soft',
                    'tournament' => 'badge-secondary badge-soft',
                    'interclub'  => 'badge-primary badge-soft',
                ];
                $typeLabels = [
                    'training'   => __('Training'),
                    'tournament' => __('Tournament'),
                    'interclub'  => __('Interclub'),
                ];
                $currentMonthKey = now()->translatedFormat('F Y');
            @endphp

            @if ($showAllEvents)
                {{-- Vue condensée avec accordéon par mois --}}
                @forelse ($calendar as $month => $events)
                    <section class="mb-4" x-data="{ open: {{ $month === $currentMonthKey ? 'true' : 'false' }} }">
                        <button type="button" class="mb-4 flex w-full items-center gap-3 text-left" @click="open = !open">
                            <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-4 py-1.5">
                                <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                <span class="text-sm font-bold uppercase tracking-wide text-gray-700">{{ $month }}</span>
                                <span class="text-xs text-gray-700 opacity-60">{{ count($events) }}</span>
                            </span>
                            <div class="flex-1 border-t border-gray-200"></div>
                            <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                        </button>
                        <div x-show="open" x-collapse>
                            <div class="divide-y divide-base-200 overflow-hidden rounded-xl border border-base-200 bg-base-100">
                                @foreach ($events as $event)
                                    @php
                                        $dt = \Carbon\Carbon::parse($event['startDateTime']);
                                        $userWaiting = ($event['registrationStatus'] ?? null) === 'waiting';
                                    @endphp
                                    <div class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-base-200/30">
                                        <div class="{{ $typeColors[$event['type']] ?? 'bg-base-300' }} h-1.5 w-1.5 shrink-0 rounded-full"></div>
                                        <span class="min-w-[52px] text-[11px] font-bold text-base-content/50">{{ $dt->translatedFormat('d M') }}</span>
                                        <div class="flex min-w-0 flex-1 flex-col">
                                            <span class="truncate text-xs font-medium">{{ $event['title'] }}</span>
                                            @if ($event['type'] === 'training' && ! empty($event['coach']))
                                                <span class="truncate text-[10px] text-base-content/40">{{ $event['coach'] }}</span>
                                            @endif
                                        </div>
                                        <span class="shrink-0 text-[11px] text-base-content/40">
                                            {{ $dt->format('H:i') }}{{ $event['type'] === 'training' && ! empty($event['endTime']) ? '–' . $event['endTime'] : '' }}
                                        </span>
                                        @if ($userWaiting)
                                            <span class="badge badge-xs badge-warning badge-soft font-bold">
                                                {{ __('Wait') }}{{ ! empty($event['waitlistPosition']) ? ' #' . $event['waitlistPosition'] : '' }}
                                            </span>
                                        @endif
                                        <span class="badge badge-xs {{ $typeBadgeClasses[$event['type']] ?? 'badge-ghost' }}">
                                            {{ $typeLabels[$event['type']] ?? $event['type'] }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="flex flex-col items-center py-16 text-base-content/40">
                        <x-icon class="mb-3 h-10 w-10" name="o-calendar" />
                        <p class="text-sm">{{ __('No upcoming events.') }}</p>
                    </div>
                @endforelse
            @else
                {{-- Vue personnelle : accordéon par mois avec statut et actions --}}
                @forelse ($calendar as $month => $events)
                    <section class="mb-4" x-data="{ open: {{ $month === $currentMonthKey ? 'true' : 'false' }} }">
                        <button type="button" class="mb-4 flex w-full items-center gap-3 text-left" @click="open = !open">
                            <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-4 py-1.5">
                                <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                <span class="text-sm font-bold uppercase tracking-wide text-gray-700">{{ $month }}</span>
                                <span class="text-xs text-gray-700 opacity-60">{{ count($events) }}</span>
                            </span>
                            <div class="flex-1 border-t border-gray-200"></div>
                            <x-icon name="o-chevron-down" class="h-4 w-4 opacity-40 transition-transform duration-200" ::class="open ? '' : '-rotate-90'" />
                        </button>
                        <div x-show="open" x-collapse>
                            @foreach ($events as $event)
                                @php
                                    $regStatus     = $event['registrationStatus'] ?? null;
                                    $isActive      = in_array($regStatus, ['registered', 'confirmed']);
                                    $isSpotOffered = $regStatus === 'spot_offered';
                                    $isWaiting     = $regStatus === 'waiting';
                                    $isTraining    = $event['type'] === 'training';
                                    $isInterclub   = $event['type'] === 'interclub';
                                @endphp
                                <x-admin.shared.compact-event-preview
                                    :name="$event['title']"
                                    :startDateTime="$event['startDateTime']"
                                    :endTime="$isTraining ? ($event['endTime'] ?? null) : null"
                                    :type="$event['type']"
                                    :link="$isInterclub && $event['isUserInTeam'] ? route('admin.interclubs.my-matches') : '#'"
                                    :location="$isInterclub ? $event['address'] : ($event['room'] ?? '')"
                                    :organizer="$isTraining && ! empty($event['coach']) ? $event['coach'] : null"
                                >
                                    <x-slot:actions>
                                        @if ($isInterclub)
                                            @if ($event['isHome'])
                                                <x-badge class="badge-neutral badge-xs font-bold" value="{{ __('Home') }}" />
                                            @else
                                                <x-badge class="badge-ghost badge-xs border border-base-300 font-bold" value="{{ __('Away') }}" />
                                            @endif
                                            @if ($event['division'])
                                                <x-badge class="badge-outline badge-xs" value="{{ $event['division'] }}" />
                                            @endif
                                            @if ($event['isUserInTeam'])
                                                @if ($event['isSelected'])
                                                    <x-badge class="badge-primary badge-sm font-bold" value="{{ __('Selected') }}" icon="o-check" />
                                                @elseif ($event['availability'])
                                                    <x-badge :class="$event['availability']->color() . ' badge-sm font-bold'" :value="$event['availability']->label()" />
                                                @else
                                                    <x-badge class="badge-ghost badge-sm" value="{{ __('No response') }}" />
                                                @endif
                                            @endif
                                        @elseif ($isTraining)
                                            @php $packStatus = $event['packStatus'] ?? 'enrolled'; @endphp
                                            @if (isset($event['level']))
                                                <x-badge class="badge-primary badge-soft badge-sm" value="{{ $event['level'] }}" />
                                            @endif
                                            @if ($packStatus === 'offered')
                                                <span class="flex items-center gap-1.5 text-xs font-semibold text-success">
                                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-success"></span>
                                                    {{ __('Confirm attendance') }}
                                                </span>
                                                @if (!empty($event['confirmDeadline']))
                                                    <span class="text-[10px] text-base-content/40">{{ \Carbon\Carbon::parse($event['confirmDeadline'])->format('d/m') }}</span>
                                                @endif
                                            @elseif ($packStatus === 'pending')
                                                <x-badge class="badge-warning badge-sm" value="{{ __('Awaiting validation') }}" />
                                            @elseif ($packStatus === 'waiting')
                                                <x-badge class="badge-warning badge-soft badge-sm"
                                                    value="{{ __('Waitlist') }}{{ !empty($event['packWaitlistPosition']) ? ' #' . $event['packWaitlistPosition'] : '' }}" />
                                            @else
                                                <x-badge class="badge-success badge-sm" value="{{ __('Enrolled') }}" />
                                            @endif
                                        @elseif ($isSpotOffered)
                                            <span class="flex items-center gap-1.5 text-xs font-semibold text-success">
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-success"></span>
                                                {{ __('Confirm attendance') }}
                                            </span>
                                            @if (!empty($event['confirmDeadline']))
                                                <span class="text-[10px] text-base-content/40">{{ \Carbon\Carbon::parse($event['confirmDeadline'])->format('d/m') }}</span>
                                            @endif
                                        @elseif ($isActive)
                                            <x-badge class="badge-success badge-sm" value="{{ __('Registered') }}" />
                                        @elseif ($isWaiting)
                                            <x-badge class="badge-warning badge-soft badge-sm"
                                                value="{{ __('Waitlist') }}{{ ! empty($event['waitlistPosition']) ? ' #' . $event['waitlistPosition'] : '' }}" />
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
                        </div>
                    </section>
                @empty
                    <div class="flex flex-col items-center py-16 text-base-content/40">
                        <x-icon class="mb-3 h-10 w-10" name="o-calendar" />
                        <p class="text-sm">{{ __('No upcoming events.') }}</p>
                    </div>
                @endforelse
            @endif
        </div>
    </div>
</div>
