@props(['events' => collect([])])

<section class="py-20 bg-base-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- En-tête --}}
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-block text-primary text-sm font-bold uppercase tracking-widest mb-3">
                {{ __('Upcoming Events') }}
            </span>
            <h2 class="text-4xl font-bold text-base-content mb-4">
                {{ __("Events not to be missed") }}
            </h2>
            <p class="text-xl text-muted max-w-3xl mx-auto">
                {{ __("Mark your calendars — these club events are worth the trip") }}
            </p>
        </div>

        {{-- Grille de cartes --}}
        <div @class([
            'grid gap-6',
            'lg:grid-cols-2'               => $events->count() === 2,
            'md:grid-cols-2 lg:grid-cols-3' => $events->count() >= 3,
        ])>
            @foreach ($events as $event)
                @php
                    $typeBar = match ($event->type->value) {
                        'TOURNAMENT' => 'bg-amber-400',
                        'TRAINING'   => 'bg-emerald-400',
                        'INTERCLUB'  => 'bg-club-blue',
                        default      => 'bg-gray-300',
                    };
                    $typeTone = match ($event->type->value) {
                        'TOURNAMENT' => 'secondary',
                        'TRAINING'   => 'success',
                        'INTERCLUB'  => 'primary',
                        default      => 'neutral',
                    };
                @endphp

                <article class="bg-base-100 border border-base-300 rounded-2xl overflow-hidden shadow-sm hover:shadow-md hover:border-primary transition-all duration-300 animate-on-scroll flex flex-col group">

                    {{-- Barre de couleur type --}}
                    <div class="h-1 {{ $typeBar }}"></div>

                    <div class="px-6 py-5 flex-1 flex flex-col">

                        {{-- Badge type + icône --}}
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="text-xl leading-none">{{ $event->icon ?? $event->type->getIcon() }}</span>
                            <x-badge :tone="$typeTone" class="uppercase tracking-widest">{{ $event->type->getLabel() }}</x-badge>
                        </div>

                        {{-- Titre --}}
                        <h3 class="text-xl font-bold text-base-content mb-2 leading-snug group-hover:text-primary transition-colors">
                            {{ $event->title }}
                        </h3>

                        {{-- Description --}}
                        @if ($event->description)
                            <p class="text-subtle text-sm mb-5 line-clamp-2 leading-relaxed">
                                {{ $event->description }}
                            </p>
                        @endif

                        {{-- Détails --}}
                        <div class="mt-auto space-y-2 text-sm text-muted pt-4 border-t border-base-300">

                            <div class="flex items-center gap-2">
                                <x-icon name="o-calendar" class="w-4 h-4 shrink-0 text-subtle" />
                                <span class="font-medium text-muted">
                                    {{ $event->event_date->isoFormat('dddd D MMMM YYYY') }}
                                </span>
                                @if ($event->start_time)
                                    <span class="text-subtle">·</span>
                                    <span>{{ $event->formatted_time }}</span>
                                @endif
                            </div>

                            @if ($event->location)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-map-pin" class="w-4 h-4 shrink-0 text-subtle" />
                                    <span>{{ $event->location }}</span>
                                </div>
                            @endif

                            @if ($event->price)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-currency-euro" class="w-4 h-4 shrink-0 text-subtle" />
                                    <span>{{ $event->price }}</span>
                                </div>
                            @endif

                            @if ($event->max_participants)
                                <div class="flex items-center gap-2">
                                    <x-icon name="o-user-group" class="w-4 h-4 shrink-0 text-subtle" />
                                    <span>{{ __('Max :n participants', ['n' => $event->max_participants]) }}</span>
                                </div>
                            @endif

                        </div>

                        {{-- Notes (optionnel) --}}
                        @if ($event->notes)
                            <p class="mt-4 text-xs text-subtle italic">{{ $event->notes }}</p>
                        @endif

                    </div>
                </article>
            @endforeach
        </div>

    </div>
</section>
