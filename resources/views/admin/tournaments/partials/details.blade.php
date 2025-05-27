<div class="bg-neutral-100 rounded-lg p-5 my-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="flex items-center">
            <div class="rounded-full bg-purple-100 p-3 mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <div class="text-gray-500">
                <p class="text-sm text-gray-500">{{ __('Hours') }}</p>
                <p class="font-bold text-lg">{{ $tournament->start_date->format('d/m/Y') }} |
                    {{ $tournament->start_date->format('H:i') }} -
                    {{ $tournament->end_date->format('H:i') }}</p>
            </div>
        </div>
        <div class="flex items-center">
            <div class="rounded-full bg-red-100 p-3 mr-3">

                <x-ui.icon name="people" class="h-6 w-6 text-red-600" />

            </div>
            <div class="text-gray-500">
                <p class="text-sm">{{ __('Registered Players') }}</p>
                <p class="font-bold text-lg">
                    {{ $tournament->total_users }}/{{ $tournament->max_users }}
                </p>
            </div>
        </div>
        <div class="flex items-center">
            <div class="rounded-full bg-orange-100 p-3 mr-3">
                <x-ui.icon name="building" class="h-6 w-6 text-orange-600" />
            </div>
            <div class="text-gray-500">
                <p class="text-sm">{{ __('Rooms') }}</p>
                @foreach ($tournament->rooms as $room)
                    <p class="font-bold text-lg">{{ $room->name }}</p>
                @endforeach
            </div>
        </div>
        <div class="flex items-center">
            <div class="rounded-full bg-green-100 p-3 mr-3">
                <x-ui.icon name="table" class="h-6 w-6 text-green-600" />
            </div>
            <div class="text-gray-500">
                <p class="text-sm">{{ __('Number of tables') }}</p>
                <p class="font-bold text-lg">{{ $tournament->tables->count() }}</p>
            </div>
        </div>
        <div class="flex items-center">
            <div class="rounded-full bg-violet-100 p-3 mr-3">
                <x-ui.icon name="results" class="h-6 w-6 text-violet-600" />
            </div>
            <div class="text-gray-500">
                <p class="text-sm">{{ __('Number of pools') }}</p>
                <p class="font-bold text-lg">{{ $tournament->pools->count() }}</p>
            </div>
        </div>
        <div class="flex items-center">
            <div class="rounded-full bg-yellow-100 p-3 mr-3">
                <!-- SVG de la balance ici -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round">
                    <line x1="12" y1="3" x2="12" y2="21" />
                    <line x1="4" y1="7" x2="20" y2="7" />
                    <line x1="9" y1="21" x2="15" y2="21" />
                    <circle cx="7" cy="14" r="3" />
                    <circle cx="17" cy="14" r="3" />
                    <line x1="7" y1="7" x2="7" y2="11" />
                    <line x1="17" y1="7" x2="17" y2="11" />
                </svg>
            </div>
            <div class="text-gray-500">
                <p class="text-sm">Handicap Points</p>
                <p class="font-bold text-lg">
                    {{ $tournament->has_handicap_points ? __('Yes') : __('No') }}
                </p>
            </div>
        </div>
    </div>
</div>
