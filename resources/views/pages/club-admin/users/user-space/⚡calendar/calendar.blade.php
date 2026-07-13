<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator :subtitle="__('Upcoming club activities')" :title="__('Calendar')">
        <x-slot:actions>
            <x-button class="btn-outline btn-sm" icon="o-calendar-days" :label="__('Subscribe (Google/Apple)')"
                wire:click="$set('icsModal', true)" responsive />
        </x-slot:actions>
    </x-header>

    {{-- Modal abonnement ICS --}}
    <x-modal wire:model="icsModal" :title="__('Subscribe to my calendar')" box-class="max-w-lg">
        <div class="space-y-4">
            <p class="text-sm text-base-content/70">
                {{ __('Add this personal link to Google Calendar or Apple Calendar to see all your club activities (matches, trainings, tournaments, meetings) update automatically.') }}
            </p>

            <div class="flex items-center gap-2" x-data="{ copied: false }">
                <input type="text" readonly value="{{ $this->icsUrl }}"
                    class="input input-sm input-bordered w-full font-mono text-xs"
                    @focus="$el.select()" />
                <x-button class="btn-primary btn-sm shrink-0"
                    x-on:click="navigator.clipboard.writeText('{{ $this->icsUrl }}'); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-show="! copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                </x-button>
            </div>

            <div class="rounded-lg border border-base-200 bg-base-200/40 p-3 text-xs text-base-content/60 space-y-1.5">
                <p><strong>Google Calendar</strong> — {{ __('Settings → Add calendar → From URL, then paste the link.') }}</p>
                <p><strong>Apple Calendar</strong> — {{ __('File → New Calendar Subscription, then paste the link.') }}</p>
                <p>{{ __('Keep this link private: anyone who has it can read your club schedule.') }}</p>
            </div>
        </div>
        <x-slot:actions>
            <x-button :label="__('Close')" wire:click="$set('icsModal', false)" />
        </x-slot:actions>
    </x-modal>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">

        <div class="space-y-4">
            <x-card class="border border-primary/20 bg-primary/5" shadow :title="__('Filters')">
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
                            :placeholder="__('All categories')"
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
                    'meeting'    => 'bg-warning',
                ];
                $typeBadgeClasses = [
                    'training'   => 'badge-accent badge-soft',
                    'tournament' => 'badge-secondary badge-soft',
                    'interclub'  => 'badge-primary badge-soft',
                    'meeting'    => 'badge-warning badge-soft',
                ];
                $typeLabels = [
                    'training'   => __('Training'),
                    'tournament' => __('Tournament'),
                    'interclub'  => __('Interclub'),
                    'meeting'    => __('Meeting'),
                ];
                $currentMonthKey = now()->translatedFormat('F Y');
            @endphp

            @if ($showAllEvents)
                {{-- Vue condensée avec accordéon par mois --}}
                @forelse ($calendar as $month => $events)
                    <x-section-accordion
                        label="{{ $month }}"
                        count="{{ count($events) }}"
                        color="gray"
                        :open="$month === $currentMonthKey"
                        class="mb-4">
                        <div>
                            <div class="divide-y divide-base-200 overflow-hidden rounded-xl border border-base-200 bg-base-100">
                                @foreach ($events as $event)
                                    @php
                                        $dt = \Carbon\Carbon::parse($event['startDateTime']);
                                        $userWaiting = ($event['registrationStatus'] ?? null) === 'waiting';
                                    @endphp
                                    <div class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-base-200/30">
                                        <div class="{{ $typeColors[$event['type']] ?? 'bg-base-300' }} h-1.5 w-1.5 shrink-0 rounded-full"></div>
                                        <span class="min-w-[52px] text-xs font-bold text-base-content/50">{{ $dt->translatedFormat('d M') }}</span>
                                        <div class="flex min-w-0 flex-1 flex-col">
                                            <span class="truncate text-xs font-medium">{{ $event['title'] }}</span>
                                            @if ($event['type'] === 'training' && ! empty($event['coach']))
                                                <span class="truncate text-xs text-base-content/50">{{ $event['coach'] }}</span>
                                            @endif
                                        </div>
                                        <span class="shrink-0 text-xs text-base-content/50">
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
                    </x-section-accordion>
                @empty
                    <div class="flex flex-col items-center py-16 text-base-content/40">
                        <x-icon class="mb-3 h-10 w-10" name="o-calendar" />
                        <p class="text-sm">{{ __('No upcoming events.') }}</p>
                    </div>
                @endforelse
            @else
                {{-- Vue personnelle : accordéon par mois avec statut et actions --}}
                @forelse ($calendar as $month => $events)
                    <x-section-accordion
                        label="{{ $month }}"
                        count="{{ count($events) }}"
                        color="gray"
                        :open="$month === $currentMonthKey"
                        class="mb-4">
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
                                                    <x-admin.shared.status-badge status="selected" />
                                                @elseif ($event['availability'])
                                                    <x-badge :class="$event['availability']->color() . ' badge-sm font-bold'" :value="$event['availability']->label()" />
                                                @else
                                                    <x-admin.shared.status-badge status="no_response" />
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
                                                    <span class="text-xs text-base-content/50">{{ \Carbon\Carbon::parse($event['confirmDeadline'])->format('d/m') }}</span>
                                                @endif
                                            @elseif ($packStatus === 'pending')
                                                <x-admin.shared.status-badge status="pending" />
                                            @elseif ($packStatus === 'waiting')
                                                <x-admin.shared.status-badge status="waiting" :detail="$event['packWaitlistPosition'] ?? null" />
                                            @else
                                                <x-admin.shared.status-badge status="enrolled" />
                                            @endif
                                        @elseif ($isSpotOffered)
                                            <span class="flex items-center gap-1.5 text-xs font-semibold text-success">
                                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-success"></span>
                                                {{ __('Confirm attendance') }}
                                            </span>
                                            @if (!empty($event['confirmDeadline']))
                                                <span class="text-xs text-base-content/50">{{ \Carbon\Carbon::parse($event['confirmDeadline'])->format('d/m') }}</span>
                                            @endif
                                        @elseif ($isActive)
                                            <x-admin.shared.status-badge status="registered" />
                                        @elseif ($isWaiting)
                                            <x-admin.shared.status-badge status="waiting" :detail="$event['waitlistPosition'] ?? null" />
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
                    </x-section-accordion>
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
