<div class="w-full max-w-md bg-white rounded-lg border border-gray-200 shadow-sm hover:outline hover:outline-blue-800">


    <!-- Header -->
    <div class="p-6 space-y-1">
        <div class="flex items-start justify-between">
            <div class="space-y-1">
                <h3 class="text-xl font-semibold text-gray-900">{{ $tournament->name }}</h3>
                <span
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    TO DO// Tournam ent
                </span>
            </div>
            <x-bi-trophy class=" fill-amber-700"/>
        </div>
    </div>

    <div class="px-6 pb-6 space-y-4">
        <div class="grid gap-3">
            <div class="flex items-center gap-3 text-sm text-gray-600">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>{{ $tournament->start_date->format('d/m/Y') }}</span>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-600">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>{{ $tournament->start_date->format('H:i') }} - {{ $tournament->end_date->format('H:i') }}</span>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-600">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>{{ $tournament->rooms()->first()->street }}<br>{{ $tournament->rooms()->first()->city_code }} {{ $tournament->rooms()->first()->city_name }}</span>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-600">
                <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                </svg>
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
        @auth    
            <button wire:click="register"
                class="flex-1 {{ $isRegistered ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700' }} text-white font-medium py-2 px-4 rounded-md transition-colors duration-200"
                {{ $isRegistered ? 'disabled' : '' }}>
                {{ $isRegistered ? 'Registered ✓' : 'Register Now' }}
                </button>
                <button wire:click="viewDetails"
                class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors duration-200">
                {{ __('View Details') }}
            </button>
        @endauth
    </div>
        @if (session()->has('message'))
        <div class="p-4 mt-4 text-sm text-green-800 bg-green-100 rounded-t-lg">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
            <div class="p-4 mt-4 text-sm text-red-800 bg-red-100 rounded-t-lg">
                {{ session('error') }}
            </div>
    @endif
</div>
