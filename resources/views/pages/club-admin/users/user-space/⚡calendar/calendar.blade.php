<x-slot:breadcrumbs>
    <x-breadcrumbs :items="$breadcrumbs" separator="o-slash" />
</x-slot:breadcrumbs>

<div>
    <x-header separator :subtitle="__('Your club activities, month by month')" :title="__('Calendar')">
        <x-slot:actions>
            <x-admin.shared.mobile-header-actions :filter-count="count($filterChips)"
                :show-search="false" :show-more="false" />
            <div class="hidden items-center gap-2 lg:flex">
                <x-admin.shared.filters-button :count="count($filterChips)" />
            </div>
            <x-button class="btn-outline btn-sm" icon="o-calendar-days" :label="__('Subscribe (Google/Apple)')"
                wire:click="$set('icsModal', true)" responsive />
        </x-slot:actions>
    </x-header>

    {{-- Mode de vue (R2) : segmented control attaché au contenu, jamais dans le drawer --}}
    <div class="mb-4 mt-1">
        <div class="flex w-full gap-1 rounded-full bg-base-200 p-1 sm:inline-flex sm:w-auto">
            <button type="button" wire:click="$set('showAllEvents', false)"
                @class(['flex-1 rounded-full px-4 py-1.5 text-center text-sm font-semibold transition-all sm:flex-none',
                    'bg-base-100 text-primary shadow-sm' => ! $showAllEvents,
                    'text-base-content/60 hover:text-base-content' => $showAllEvents])>
                {{ __('My events') }}
            </button>
            <button type="button" wire:click="$set('showAllEvents', true)"
                @class(['flex-1 rounded-full px-4 py-1.5 text-center text-sm font-semibold transition-all sm:flex-none',
                    'bg-base-100 text-primary shadow-sm' => $showAllEvents,
                    'text-base-content/60 hover:text-base-content' => ! $showAllEvents])>
                {{ __('All club events') }}
            </button>
        </div>
    </div>

    <x-admin.shared.filter-chips :chips="$filterChips" />

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

    @php
        $typeDotClasses = [
            'training'   => 'bg-accent',
            'tournament' => 'bg-secondary',
            'interclub'  => 'bg-primary',
            'meeting'    => 'bg-warning',
        ];
        $typeChipClasses = [
            'training'   => 'border-accent bg-accent/10',
            'tournament' => 'border-secondary bg-secondary/20',
            'interclub'  => 'border-primary bg-primary/10',
            'meeting'    => 'border-warning bg-warning/20',
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
        $weekStart = now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $gridDays = collect($weeks)->flatten(1);
        $monthActions = 'previousMonth,nextMonth,goToToday,setMonth,toggleCategory,showAllEvents';
    @endphp

    {{--
        Sélection de jour 100 % côté client : toutes les listes de jours sont
        rendues côté serveur, Alpine ne fait que basculer leur visibilité.
        `selectedDay` reste entangled (deferred) pour survivre à la navigation
        de mois et rester dans l'URL.
    --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start"
        x-data="{
            selected: $wire.entangle('selectedDay'),
            touchX: 0,
            touchY: 0,
            select(day) {
                this.selected = day;
                if (window.innerWidth < 1024) {
                    this.$nextTick(() => this.$refs.dayPanel.scrollIntoView({
                        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                        block: 'start',
                    }));
                }
            },
        }"
        x-on:keydown.arrow-left.window="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) $wire.previousMonth()"
        x-on:keydown.arrow-right.window="if (! ['INPUT', 'TEXTAREA', 'SELECT'].includes($event.target.tagName)) $wire.nextMonth()">

        {{-- Grille mensuelle (swipe gauche/droite = changer de mois) --}}
        <div class="min-w-0 flex-1 overflow-hidden rounded-xl border border-base-200 bg-base-100"
            x-on:touchstart.passive="touchX = $event.touches[0].clientX; touchY = $event.touches[0].clientY"
            x-on:touchend="
                const dx = $event.changedTouches[0].clientX - touchX;
                const dy = $event.changedTouches[0].clientY - touchY;
                if (Math.abs(dx) > 60 && Math.abs(dy) < 40) { dx < 0 ? $wire.nextMonth() : $wire.previousMonth() }
            ">
            <div class="flex items-center justify-between gap-2 border-b border-base-200 px-3 py-2.5 sm:px-4">
                <div class="flex items-center gap-1">
                    <x-button class="btn-ghost btn-sm btn-square" icon="o-chevron-left"
                        wire:click="previousMonth" wire:loading.attr="disabled" wire:target="{{ $monthActions }}"
                        :aria-label="__('Previous month')" />

                    {{-- Titre cliquable : mini-picker mois/année --}}
                    <div class="relative" x-data="{ open: false, year: {{ $pickerYear }} }"
                        x-on:click.outside="open = false" x-on:keydown.escape="open = false"
                        wire:key="month-picker-{{ $month }}">
                        <button type="button"
                            class="btn btn-ghost btn-sm min-w-36 text-sm font-bold sm:text-base"
                            x-on:click="open = ! open" :aria-expanded="open"
                            aria-haspopup="true" aria-label="{{ __('Choose month') }}">
                            {{ $monthLabel }}
                        </button>
                        <div x-show="open" x-transition.opacity.duration.150ms style="display:none"
                            class="absolute left-1/2 z-20 mt-1 w-60 -translate-x-1/2 rounded-xl border border-base-200 bg-base-100 p-3 shadow-lg">
                            <div class="mb-2 flex items-center justify-between">
                                <button type="button" class="btn btn-ghost btn-xs btn-square"
                                    x-on:click="year--" aria-label="{{ __('Previous') }}">‹</button>
                                <span class="text-sm font-bold tabular-nums" x-text="year"></span>
                                <button type="button" class="btn btn-ghost btn-xs btn-square"
                                    x-on:click="year++" aria-label="{{ __('Next') }}">›</button>
                            </div>
                            <div class="grid grid-cols-3 gap-1">
                                @foreach ($monthShortNames as $i => $shortName)
                                    <button type="button" class="btn btn-ghost btn-xs"
                                        x-on:click="open = false; $wire.setMonth(year + '-' + String({{ $i + 1 }}).padStart(2, '0'))">
                                        {{ $shortName }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <x-button class="btn-ghost btn-sm btn-square" icon="o-chevron-right"
                        wire:click="nextMonth" wire:loading.attr="disabled" wire:target="{{ $monthActions }}"
                        :aria-label="__('Next month')" />
                </div>
                @unless ($isCurrentMonth)
                    <x-button class="btn-outline btn-xs" :label="__('Today')" wire:click="goToToday"
                        wire:loading.attr="disabled" wire:target="{{ $monthActions }}" />
                @endunless
            </div>

            <div wire:loading.class="pointer-events-none opacity-50" wire:target="{{ $monthActions }}"
                class="transition-opacity">
                {{-- En-têtes des jours --}}
                <div class="grid grid-cols-7 border-b border-base-200 bg-base-200/40">
                    @for ($i = 0; $i < 7; $i++)
                        <div class="px-1 py-1.5 text-center text-[10px] font-bold uppercase tracking-wide text-base-content/50 lg:text-left lg:px-2">
                            {{ $weekStart->copy()->addDays($i)->translatedFormat('D') }}
                        </div>
                    @endfor
                </div>

                {{-- Semaines --}}
                @foreach ($weeks as $week)
                    <div class="grid grid-cols-7" wire:key="week-{{ $week[0]['date'] }}">
                        @foreach ($week as $day)
                            <button type="button" wire:key="day-{{ $day['date'] }}"
                                x-on:click="select('{{ $day['date'] }}')"
                                aria-label="{{ $day['ariaLabel'] }}"
                                @if ($day['isToday']) aria-current="date" @endif
                                :aria-pressed="(selected === '{{ $day['date'] }}').toString()"
                                class="group relative flex min-h-12 flex-col items-center gap-0.5 border-b border-r border-base-200/60 p-1 transition-colors last:border-r-0 lg:min-h-24 lg:items-stretch lg:p-1.5"
                                :class="selected === '{{ $day['date'] }}' ? 'bg-primary/5' : 'hover:bg-base-200/40'">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold lg:h-5 lg:w-5 lg:text-[11px]"
                                    :class="selected === '{{ $day['date'] }}'
                                        ? 'bg-primary text-primary-content{{ $day['isToday'] ? ' ring-2 ring-primary/40 ring-offset-1 ring-offset-base-100' : '' }}'
                                        : '{{ $day['isToday'] ? 'text-primary font-bold ring-1 ring-primary' : ($day['inMonth'] ? 'text-base-content/70' : 'text-base-content/30') }}'">
                                    {{ $day['day'] }}
                                </span>

                                {{-- Mobile : pastilles --}}
                                <span class="flex h-1.5 items-center justify-center gap-0.5 lg:hidden">
                                    @foreach (array_slice($day['events'], 0, 3) as $event)
                                        <span @class([
                                            'h-1.5 w-1.5 rounded-full',
                                            $typeDotClasses[$event['type']] ?? 'bg-base-300',
                                            'opacity-40' => $day['isPast'],
                                        ])></span>
                                    @endforeach
                                    @if (count($day['events']) > 3)
                                        <span class="text-[8px] font-bold leading-none text-base-content/50">+{{ count($day['events']) - 3 }}</span>
                                    @endif
                                </span>

                                {{-- Desktop : chips --}}
                                <span class="hidden w-full min-w-0 flex-col gap-0.5 lg:flex">
                                    @foreach (array_slice($day['events'], 0, 3) as $event)
                                        <span @class([
                                            'block truncate rounded border-l-[3px] px-1 py-0.5 text-left text-[11px] font-medium leading-tight',
                                            $typeChipClasses[$event['type']] ?? 'border-base-300 bg-base-200',
                                            'opacity-50' => $day['isPast'],
                                        ])>
                                            @if (($event['dayIndex'] ?? 1) > 1)
                                                {{ __('Day :current/:total', ['current' => $event['dayIndex'], 'total' => $event['dayCount']]) }}
                                            @else
                                                {{ \Carbon\Carbon::parse($event['startDateTime'])->format('H:i') }}
                                            @endif
                                            {{ $event['title'] }}
                                        </span>
                                    @endforeach
                                    @if (count($day['events']) > 3)
                                        <span class="px-1 text-left text-[10px] font-semibold text-base-content/50">
                                            +{{ count($day['events']) - 3 }}
                                        </span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                @endforeach

                {{-- Mois vide : un nouveau membre ne doit pas croire à un bug --}}
                @unless ($monthHasEvents)
                    <div class="px-4 py-6 text-center">
                        <p class="text-sm text-base-content/50">{{ __('No events this month.') }}</p>
                        @unless ($showAllEvents)
                            <x-button class="btn-outline btn-xs mt-2" :label="__('All club events')"
                                wire:click="$set('showAllEvents', true)" />
                        @endunless
                    </div>
                @endunless
            </div>

            {{-- Légende cliquable : raccourci de filtre par catégorie --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-base-200 px-3 py-2 sm:px-4">
                @foreach ($typeLabels as $type => $label)
                    <button type="button" wire:click="toggleCategory('{{ $type }}')"
                        :aria-pressed="@js(in_array($type, $selectedCategories))"
                        @class([
                            'flex items-center gap-1.5 text-[11px] transition-opacity',
                            'text-base-content/60 hover:text-base-content' => $selectedCategories === [] || in_array($type, $selectedCategories),
                            'opacity-35 line-through hover:opacity-70' => $selectedCategories !== [] && ! in_array($type, $selectedCategories),
                        ])>
                        <span class="{{ $typeDotClasses[$type] }} h-2 w-2 rounded-full"></span>
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Panneau du jour : toutes les listes sont pré-rendues, Alpine bascule --}}
        <div class="w-full scroll-mt-20 rounded-xl border border-base-200 bg-base-100 p-4 lg:w-96 lg:shrink-0"
            x-ref="dayPanel" aria-live="polite" wire:key="day-panel">
            @foreach ($gridDays as $day)
                <div x-show="selected === '{{ $day['date'] }}'" wire:key="panel-{{ $day['date'] }}"
                    @if ($day['date'] !== $selectedDay) style="display:none" @endif>
                    <h3 class="mb-1 text-sm font-bold">{{ $day['panelLabel'] }}</h3>

                    @forelse ($day['events'] as $event)
                        @php
                            $regStatus     = $event['registrationStatus'] ?? null;
                            $isActive      = in_array($regStatus, ['registered', 'confirmed']);
                            $isSpotOffered = $regStatus === 'spot_offered';
                            $isWaiting     = $regStatus === 'waiting';
                            $isTraining    = $event['type'] === 'training';
                            $isInterclub   = $event['type'] === 'interclub';
                        @endphp
                        <x-admin.shared.compact-event-preview
                            wire:key="event-{{ $day['date'] }}-{{ $loop->index }}"
                            :name="$event['title']"
                            :startDateTime="$event['startDateTime']"
                            :endTime="$isTraining ? ($event['endTime'] ?? null) : null"
                            :type="$event['type']"
                            :link="$isInterclub && $event['isUserInTeam'] ? route('admin.interclubs.my-matches') : '#'"
                            :location="$isInterclub ? $event['address'] : ($event['room'] ?? '')"
                            :organizer="$isTraining && ! empty($event['coach']) ? $event['coach'] : null"
                        >
                            <x-slot:actions>
                                @if (($event['dayIndex'] ?? 1) > 1)
                                    <x-badge class="badge-ghost badge-xs"
                                        value="{{ __('Day :current/:total', ['current' => $event['dayIndex'], 'total' => $event['dayCount']]) }}" />
                                @endif
                                @if ($showAllEvents)
                                    @if (($event['registrationStatus'] ?? null) === 'waiting')
                                        <span class="badge badge-xs badge-warning badge-soft font-bold">
                                            {{ __('Wait') }}{{ ! empty($event['waitlistPosition']) ? ' #' . $event['waitlistPosition'] : '' }}
                                        </span>
                                    @endif
                                    <span class="badge badge-xs {{ $typeBadgeClasses[$event['type']] ?? 'badge-ghost' }}">
                                        {{ $typeLabels[$event['type']] ?? $event['type'] }}
                                    </span>
                                @elseif ($isInterclub)
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
                                        @if (! empty($event['confirmDeadline']))
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
                                    @if (! empty($event['confirmDeadline']))
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
                            </x-slot:actions>
                        </x-admin.shared.compact-event-preview>
                    @empty
                        <p class="py-6 text-center text-sm text-base-content/40">
                            {{ __('No events on this day.') }}
                        </p>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>

    {{-- Drawer de filtres (R-filtres) --}}
    <x-admin.shared.filter-drawer :title="__('Filters')">
        <x-slot:filters>
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide opacity-60">{{ __('Category') }}</p>
                <div class="space-y-2">
                    @foreach ($categories as $category)
                        <x-checkbox :label="$category['name']" :value="$category['id']"
                            wire:model.live="selectedCategories" />
                    @endforeach
                </div>
            </div>
        </x-slot:filters>
    </x-admin.shared.filter-drawer>
</div>
