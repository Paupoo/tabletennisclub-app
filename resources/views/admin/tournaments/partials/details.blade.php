<div class="max-w-6xl mx-auto">
    <h3 class="text-xl font-bold text-gray-800 my-8">Paramètres du tournoi</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <!-- Horaires -->
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center">
                    <x-ui.icon name="calendar" class="h-6 w-6 text-purple-600"/>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 mb-1">{{ __('Schedules') }}</p>
                <p class="text-lg font-semibold text-gray-900 leading-tight">
                    {{ $tournament->start_date->format('d/m/Y') }}
                <p class="text-base text-gray-700">{{ $tournament->start_date->format('H:i') }} -
                    {{ $tournament->end_date->format('H:i') }}</p>
            </div>
        </div>

        <!-- Salles -->
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center">
                    <x-ui.icon name="building" class="h-6 w-6 text-orange-600" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 mb-1">{{ 'Rooms' }}</p>
                <ul class="list-disc ml-4">
                    @foreach ($tournament->rooms as $room)
                        <li class="text-lg font-semibold text-gray-900">{{ $room->name }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Nombre de poules -->
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-violet-100 flex items-center justify-center">
                    <x-ui.icon name="results" class="h-6 w-6 text-violet-600" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 mb-1">{{ __('Number of pools') }}</p>
                <p class="text-lg font-semibold text-gray-900">{{ $tournament->pools->count() }}</p>
            </div>
        </div>

        <!-- Joueurs inscrits -->
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                    <x-ui.icon name="people" class="h-6 w-6 text-red-600" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 mb-1">{{ __('Registered Players') }}</p>
                <p class="text-lg font-semibold text-gray-900">{{ $tournament->total_users }} /
                    {{ $tournament->max_users }}</p>
            </div>
        </div>

        <!-- Nombre de tables -->
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                    <x-ui.icon name="table" class="h-6 w-6 text-green-600" />
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 mb-1">{{  __('Number of tables') }}</p>
                <p class="text-lg font-semibold text-gray-900">{{ $tournament->tables->count() }}</p>
            </div>
        </div>


        <!-- Points handicap -->
        <div class="flex items-start space-x-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 rounded-full bg-yellow-100 flex items-center justify-center">
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
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 mb-1">{{ __('Handicap Points') }}</p>
                <p class="text-lg font-semibold text-gray-900">{{ $tournament->has_handicap_points ? ('Yes') : ('No') }}</p>
            </div>
        </div>
    </div>
</div>
