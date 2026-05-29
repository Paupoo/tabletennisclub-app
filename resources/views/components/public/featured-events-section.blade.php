@props(['events' => collect([])])

<section class="py-20 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        {{-- En-tête --}}
        <div class="text-center mb-16 animate-on-scroll">
            <span class="inline-block text-club-blue text-sm font-bold uppercase tracking-widest mb-3">
                {{ __('Upcoming Events') }}
            </span>
            <h2 class="text-4xl font-bold text-gray-900 mb-4">
                {{ __("Events not to be missed") }}
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
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
                    $typeAccent = match ($event->type->value) {
                        'TOURNAMENT' => ['bar' => 'bg-amber-400',   'badge' => 'bg-amber-50 text-amber-700 ring-amber-200'],
                        'TRAINING'   => ['bar' => 'bg-emerald-400', 'badge' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
                        'INTERCLUB'  => ['bar' => 'bg-club-blue',   'badge' => 'bg-blue-50 text-club-blue ring-blue-200'],
                        default      => ['bar' => 'bg-gray-300',    'badge' => 'bg-gray-100 text-gray-600 ring-gray-200'],
                    };
                @endphp

                <article class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm hover:shadow-md hover:border-gray-300 transition-all duration-300 animate-on-scroll flex flex-col group">

                    {{-- Barre de couleur type --}}
                    <div class="h-1 {{ $typeAccent['bar'] }}"></div>

                    <div class="px-6 py-5 flex-1 flex flex-col">

                        {{-- Badge type + icône --}}
                        <div class="flex items-center gap-2.5 mb-4">
                            <span class="text-xl leading-none">{{ $event->icon ?? $event->type->getIcon() }}</span>
                            <span class="text-xs font-semibold uppercase tracking-widest px-2.5 py-1 rounded-full ring-1 {{ $typeAccent['badge'] }}">
                                {{ $event->type->getLabel() }}
                            </span>
                        </div>

                        {{-- Titre --}}
                        <h3 class="text-xl font-bold text-gray-900 mb-2 leading-snug group-hover:text-club-blue transition-colors">
                            {{ $event->title }}
                        </h3>

                        {{-- Description --}}
                        @if ($event->description)
                            <p class="text-gray-500 text-sm mb-5 line-clamp-2 leading-relaxed">
                                {{ $event->description }}
                            </p>
                        @endif

                        {{-- Détails --}}
                        <div class="mt-auto space-y-2 text-sm text-gray-600 pt-4 border-t border-gray-100">

                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="font-medium text-gray-700">
                                    {{ $event->event_date->isoFormat('dddd D MMMM YYYY') }}
                                </span>
                                @if ($event->start_time)
                                    <span class="text-gray-400">·</span>
                                    <span>{{ $event->formatted_time }}</span>
                                @endif
                            </div>

                            @if ($event->location)
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>{{ $event->location }}</span>
                                </div>
                            @endif

                            @if ($event->price)
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span>{{ $event->price }}</span>
                                </div>
                            @endif

                            @if ($event->max_participants)
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span>{{ __('Max :n participants', ['n' => $event->max_participants]) }}</span>
                                </div>
                            @endif

                        </div>

                        {{-- Notes (optionnel) --}}
                        @if ($event->notes)
                            <p class="mt-4 text-xs text-gray-400 italic">{{ $event->notes }}</p>
                        @endif

                    </div>
                </article>
            @endforeach
        </div>

    </div>
</section>
