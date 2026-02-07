<h2 class="text-xl font-bold text-gray-800 mb-6">Liste des tables</h2>
<div class="max-w-7xl mx-auto p-6">
    <h1 class="text-2xl font-bold text-white mb-6">État des tables</h1>

    <!-- Filtres et recherche -->
    <div class="flex flex-wrap gap-4 mb-6">
        <button class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition">Toutes</button>
        <button class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Disponibles</button>
        <button class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-100 transition">Occupées</button>
        <div class="ml-auto">
            <input type="text" placeholder="Rechercher..."
                class="px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:outline-hidden">
        </div>
    </div>
    <!-- Grille des tables avec espacement et responsive améliorés -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">

        @foreach ($tables as $table)
            @if ($table->pivot->is_table_free)
                <!-- Table 1 - Disponible -->
                <div
                    class="group relative rounded-xl border border-green-400 bg-linear-to-br from-green-50 to-green-100 p-5 shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                    <div class="absolute top-3 right-3">
                        <span class="flex h-3 w-3">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-full bg-green-500 text-white font-bold text-xl shadow-inner">
                            {{ $table->name }}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-green-700 font-semibold text-lg">{{ __('Free') }}</span>
                            {{-- <span class="text-green-600 text-sm">Libre depuis {{ round($table->pivot->match_ended_at->diffInMinutes(now())) }} min</span> --}}
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            class="px-3 py-1 bg-green-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                            {{ __('Book') }}
                        </button>
                    </div>
                </div>
            @else
                {{-- Duration --}}
                @php
                    $expected_match_duration = 20;
                    $duration = round($table->pivot->match_started_at->diffInMinutes(now()));
                    $percent = min(100, ($duration / $expected_match_duration) * 100);
                @endphp

                <!-- Table 2 - Occupée -->
                <div
                    class="group relative rounded-xl border border-{{ $percent < 100 ? 'gray' : 'red' }}-400 bg-linear-to-br from-{{ $percent < 100 ? 'gray' : 'red' }}-50 to-{{ $percent < 100 ? 'gray' : 'red' }}-100 p-5 shadow-xs transition-all duration-300 hover:-translate-y-1 hover:shadow-md">
                    <div class="absolute top-3 right-3">
                        <span class="flex h-3 w-3">
                            <span
                                class="relative inline-flex rounded-full h-3 w-3 bg-{{ $percent < 100 ? 'gray' : 'red' }}-500"></span>
                        </span>
                    </div>

                    <div class="flex items-center space-x-4">
                        <div
                            class="flex items-center justify-center w-12 h-12 rounded-full bg-{{ $percent < 100 ? 'gray' : 'red' }}-500 text-white font-bold text-xl shadow-inner">
                            {{ $table->name }}
                        </div>
                        <div class="flex flex-col">
                            <span
                                class="text-{{ $percent < 100 ? 'gray' : 'red' }}-700 font-semibold text-lg">Occupée</span>
                            <span class="text-{{ $percent < 100 ? 'gray' : 'red' }}-600 text-sm">Depuis
                                {{ $duration }} min</span>
                        </div>
                    </div>

                    <div class="mt-4 bg-white rounded-lg p-4 shadow-xs">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 text-gray-600 mr-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                    </path>
                                </svg>
                                @php
                                    $match = $table->match->first();
                                @endphp
                                <span class="text-gray-800 font-medium text-sm">{{ $match->player1->first_name }}
                                    {{ $match->player1->last_name }}</span>
                            </div>
                            <span class="text-gray-500 text-xs">VS</span>
                            <div class="flex items-center">
                                <span class="text-gray-800 font-medium text-sm">{{ $match->player2->first_name }}
                                    {{ $match->player2->last_name }}</span>
                                <svg class="w-4 h-4 text-gray-600 ml-1" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <div class="flex justify-center items-center mt-2">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-{{ $percent < 100 ? 'gray' : 'red' }}-500 h-2 rounded-full"
                                    style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <a href="{{ route('editMatch', $match) }}">
                                <button type="submit"
                                    class="px-3 py-1 bg-blue-500 text-white rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    {{ __('Encode results') }}
                                </button>
                            </a>
                        </div>
                    </div>

                </div>
            @endif
        @endforeach

    </div>
</div>
