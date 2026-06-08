@props(['schedules' => [], 'scheduleContext' => null])

<section id="schedule" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">{{ __('Schedule and activities') }}</h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Rejoignez-nous pour nos séances d'entraînement régulières et nos tournois, bienvenues à tous les supporters les vendredis soir.
            </p>
        </div>

        @if ($scheduleContext !== null && in_array($scheduleContext['type'], ['future', 'upcoming'], true))
            <div class="mb-8 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-blue-800 animate-on-scroll">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium">
                    Ces horaires entrent en vigueur dès le {{ \Carbon\Carbon::parse($scheduleContext['season_start'])->translatedFormat('d F Y') }}.
                </p>
            </div>
        @elseif ($scheduleContext !== null && $scheduleContext['type'] === 'past')
            <div class="mb-8 flex items-start gap-3 rounded-xl border border-orange-200 bg-orange-50 px-5 py-4 text-orange-800 animate-on-scroll">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-sm font-medium">
                    Saison terminée – reprise prévue en septembre. Ces horaires sont ceux de la saison {{ $scheduleContext['season_name'] }}.
                </p>
            </div>
        @endif

        <!-- Affiché uniquement sur les smartphones -->
        <div class="block md:hidden">
            <x-public.schedule-mini-overview :schedules="$schedules ?? []" :compact="false" />
        </div>

        <!-- Affiché uniquement sur les tablettes (iPad par ex.) -->
        <div class="hidden md:block lg:hidden">
            <x-public.schedule-week-overview :schedules="$schedules ?? []" />
        </div>

        <!-- Affiché uniquement sur les ordinateurs -->
        <div class="hidden lg:block">
            <x-public.schedule-calendar-view :schedules="$schedules ?? []" />
        </div>

        <!-- Call to Action -->
        <div class="text-center mt-12 animate-on-scroll">
            <div class="bg-gradient-to-r from-club-blue to-club-blue-light rounded-2xl p-8 text-white">
                <h3 class="text-2xl font-bold mb-4">{{ __('Ready to get started?') }}</h3>
                <p class="text-xl mb-6 opacity-90">
                    Rejoignez-nous pour une séance d'essai gratuite !
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="#contact" class="bg-club-yellow text-club-blue px-8 py-3 rounded-lg font-semibold hover:bg-club-yellow-light transition-colors">
                        Réserver une Séance d'Essai
                    </a>
                    <a href="#join" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-club-blue transition-colors">
                        Devenir Membre
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
