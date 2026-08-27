<x-guest-layout title="Événements - {{ config('club.name') }}">

    <!-- Header -->
    <div class="relative h-auto pt-16 text-white flex items-center overflow-hidden">
        <div class="absolute inset-0">
            <img src="{{ asset('images/background_events.webp') }}" alt="Tennis table background" class="w-full h-full object-cover">
            <div class="absolute inset-0 bg-linear-to-br from-club-blue/85 via-club-blue/80 to-club-blue-light/85"></div>
        </div>
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 drop-shadow-lg">{{ __('Upcoming events') }}</h1>
            <p class="text-xl opacity-90 drop-shadow-md">{{ __('Join us for tournaments, training sessions and community events') }}</p>
        </div>
    </div>

    <livewire:public.events.event-list />

</x-guest-layout>
