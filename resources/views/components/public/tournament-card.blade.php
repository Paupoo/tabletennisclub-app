@props([
    'tournament' => [
        'name' => 'Summer Championship 2024',
        'category' => 'Esports Tournament',
        'date' => 'July 15-17, 2024',
        'time' => '9:00 AM - 6:00 PM EST',
        'location' => 'Los Angeles Convention Center',
        'participants' => '128 / 256 participants',
        'prize_pool' => '$50,000',
        'entry_fee' => '$25',
        'status' => 'Open',
        'format' => 'Single elimination bracket with best-of-3 matches in semifinals and finals.'
    ]
])

<div class="w-full max-w-md bg-white rounded-lg border border-gray-200 shadow-xs" x-data="{ showDetails: false }">
    <!-- Header -->
    <div class="p-6 space-y-1">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <h3 class="text-xl font-semibold text-gray-900">{{ $tournament->name }}</h3>
            </div>
            {{-- <svg class="h-6 w-6 text-yellow-500" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
            </svg> --}}
        </div>
    </div>

    <!-- Content -->
    <div class="px-6 pb-6 space-y-4">
        <div class="grid gap-3">
            <div class="flex items-center gap-3 text-sm text-gray-600">
                <x-icon name="o-calendar" class="h-4 w-4 text-gray-400" />
                <span>{{ $tournament->start_date->format('d/m/Y') }}</span>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-600">
                <x-icon name="o-clock" class="h-4 w-4 text-gray-400" />
                <span>{{ $tournament->start_date->format('H:i') }} - {{ $tournament->end_date->format('H:i') }}</span>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-600">
                <x-icon name="o-map-pin" class="h-4 w-4 text-gray-400" />
                <span>{{ $tournament->rooms()->first()->street }}<br>{{ $tournament->rooms()->first()->city_code }} {{ $tournament->rooms()->first()->city_name }}</span>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-600">
                <x-icon name="o-user-group" class="h-4 w-4 text-gray-400" />
                <span>{{ $tournament->total_users }} / {{ $tournament->max_users }} </span>
            </div>
        </div>

        <hr class="border-gray-200">

        <div class="space-y-2">
            <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-gray-900">{{ __('Entry Fee') }}</span>
                <span class="text-sm text-gray-600">{{ $tournament->price > 0 ? $tournament->price . ' €' : __('Free') }}</span>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-sm font-medium text-gray-900">{{ __('Handicap Points') }}</span>
                <span class="text-sm text-gray-600">{{ $tournament->has_handicap_points ? __('Yes') : __('No') }}</span>
            </div>
        </div>

        <hr class="border-gray-200">


    </div>

    <!-- Footer -->
    <div class="px-6 pb-6 flex gap-2">
        <button class="flex-1 bg-club-blue hover:bg-club-blue-light text-white font-medium py-2 px-4 rounded-md transition-colors duration-200">
            {{ __('Register Now') }}
        </button>
        <button class="flex-1 border border-gray-300 hover:border-club-blue hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors duration-200">
            {{ __('View Details') }}
        </button>
    </div>
</div>