<x-guest-layout title="Événements - Ace Table Tennis Club">

    <!-- Header -->
    <div class="relative h-auto pt-16 text-white flex items-center overflow-hidden">
        <!-- Image de fond -->
        <div class="absolute inset-0">
            <img src="{{ asset('images/background_events.webp') }}" alt="Tennis table background" class="w-full h-full object-cover">
            <!-- Overlay avec votre dégradé + opacité -->
            <div class="absolute inset-0 bg-gradient-to-br from-club-blue/85 via-club-blue/80 to-club-blue-light/85"></div>
        </div>

        <!-- Contenu -->
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 drop-shadow-lg">{{ __('Upcoming events') }}</h1>
            <p class="text-xl opacity-90 drop-shadow-md">{{ __('Join us for tournaments, training sessions and community events') }}</p>
        </div>
    </div>

    <!-- EventPost Filters -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" x-data="{ selectedCategory: 'all' }">
        @if (!empty($events ?? []))
            <div class="flex flex-wrap gap-2 mb-8">
                <button @click="selectedCategory = 'all'"
                        :class="selectedCategory === 'all' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 rounded-lg border transition-colors">
                    {{ __('All events') }}
                </button>
                <button @click="selectedCategory = 'tournament'"
                        :class="selectedCategory === 'tournament' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 rounded-lg border transition-colors">
                    {{ __('Tournaments') }}
                </button>
                <button @click="selectedCategory = 'training'"
                        :class="selectedCategory === 'training' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 rounded-lg border transition-colors">
                    {{ __('Training') }}
                </button>
                <button @click="selectedCategory = 'club-life'"
                        :class="selectedCategory === 'club-life' ? 'bg-club-blue text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                        class="px-4 py-2 rounded-lg border transition-colors">
                    {{ __('Club life') }}
                </button>
            </div>
        @endif

        <!-- Events Grid -->
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 mb-16">
            @forelse($events ?? [] as $event)
                <x-public.event-card :event="$event" />
            @empty
                <div class="col-span-full flex flex-col items-center justify-center gap-6 rounded-2xl bg-gray-50 px-6 py-20 text-center">
                    <span class="text-6xl">🏓</span>
                    <div class="max-w-md">
                        <h2 class="mb-2 text-2xl font-bold text-gray-900">{{ __('No events scheduled yet') }}</h2>
                        <p class="text-gray-500">{{ __("Events coming soon — check back, there's always something happening at the club!") }}</p>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ route('home') }}#join" class="rounded-lg bg-club-yellow px-8 py-3 font-semibold text-club-blue transition-colors hover:bg-club-yellow-light">
                            {{ __('Become a member') }}
                        </a>
                        <a href="{{ route('home') }}#contact" class="rounded-lg border-2 border-club-blue px-8 py-3 font-semibold text-club-blue transition-colors hover:bg-club-blue hover:text-white">
                            {{ __('Contact us') }}
                        </a>
                    </div>
                </div>
            @endforelse
        </div>

        <!-- Call to Action -->
        <div class="bg-club-blue rounded-lg p-8 text-white text-center">
            <h2 class="text-3xl font-bold mb-4">{{ __("Don't Miss Out!") }}</h2>
            <p class="text-xl mb-6 opacity-90">
                {{ __('Join our events and become part of the Ace TTC community. All levels welcome!') }}
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('home') }}#join" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                    {{ __('Become a member') }}
                </a>
                <a href="{{ route('home') }}#contact" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                    {{ __('Contact us') }}
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
