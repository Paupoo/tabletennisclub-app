@props(['agenda', 'scheduleContext' => null])

<section id="schedule" class="py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-10 animate-on-scroll">
            <h2 class="text-4xl font-bold text-gray-900">{{ __('Schedule and activities') }}</h2>
        </div>

        @if ($scheduleContext !== null && in_array($scheduleContext['type'], ['future', 'upcoming'], true))
            <div class="mb-8 flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 text-blue-800 animate-on-scroll">
                <x-icon name="o-information-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                <p class="text-sm font-medium">
                    Ces horaires entrent en vigueur dès le {{ \Carbon\Carbon::parse($scheduleContext['season_start'])->translatedFormat('d F Y') }}.
                </p>
            </div>
        @elseif ($scheduleContext !== null && $scheduleContext['type'] === 'past')
            <div class="mb-8 flex items-start gap-3 rounded-xl border border-orange-200 bg-orange-50 px-5 py-4 text-orange-800 animate-on-scroll">
                <x-icon name="o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                <p class="text-sm font-medium">
                    Saison terminée – reprise prévue en septembre. Ces horaires sont ceux de la saison {{ $scheduleContext['season_name'] }}.
                </p>
            </div>
        @endif

        <x-public.agenda :agenda="$agenda" />

    </div>
</section>
