@props(['schedules' => []])

@php
    $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="bg-white rounded-2xl shadow-sm border overflow-hidden" x-data="{ showDetails: false }">
    <!-- En-tête avec toggle -->
    <div class="bg-gradient-to-r from-club-blue to-club-blue-light text-white p-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold">Planning Hebdomadaire</h3>
                <p class="text-sm opacity-90 mt-1">Vue d'ensemble des activités</p>
            </div>
            
            <!-- Toggle Switch Premium -->
            <div class="flex items-center space-x-3 bg-white/10 backdrop-blur-sm rounded-full p-1">
                <span class="text-sm font-medium px-3 py-1 rounded-full transition-all duration-200"
                      :class="!showDetails ? 'bg-white text-club-blue shadow-sm' : 'text-white/70'">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Aperçu
                </span>
                <button @click="showDetails = !showDetails" 
                        class="relative inline-flex h-8 w-14 items-center rounded-full transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-club-blue"
                        :class="showDetails ? 'bg-club-yellow' : 'bg-white/20'">
                    <span class="inline-block h-6 w-6 transform rounded-full bg-white transition-all duration-300 shadow-lg"
                          :class="showDetails ? 'translate-x-7 bg-club-blue' : 'translate-x-1'">
                        <svg x-show="!showDetails" class="w-3 h-3 text-club-blue m-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <svg x-show="showDetails" class="w-3 h-3 text-white m-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </span>
                </button>
                <span class="text-sm font-medium px-3 py-1 rounded-full transition-all duration-200"
                      :class="showDetails ? 'bg-white text-club-blue shadow-sm' : 'text-white/70'">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Détails
                </span>
            </div>
        </div>
    </div>
    
    <!-- Vue grille simple -->
    <div x-show="!showDetails" x-transition>
        <div class="grid grid-cols-7 gap-0">
            @foreach($daysOfWeek as $day)
                @php
                    $dayActivities = $activitiesByDay->get($day, collect());
                    $isToday = $day === now()->locale('fr')->dayName;
                    $activityCount = $dayActivities->count();
                @endphp
                
                <div class="border-r border-b last:border-r-0 min-h-24 p-3 @if($isToday) bg-club-yellow/10 @endif hover:bg-gray-50 transition-colors">
                    <!-- En-tête du jour -->
                    <div class="text-center mb-2">
                        <div class="text-sm font-semibold @if($isToday) text-club-blue @else text-gray-700 @endif">
                            {{ $day }}
                        </div>
                        @if($isToday)
                            <div class="w-2 h-2 bg-club-yellow rounded-full mx-auto mt-1"></div>
                        @endif
                    </div>
                    
                    <!-- Indicateur d'activités -->
                    <div class="text-center">
                        @if($activityCount > 0)
                            <div class="inline-flex items-center justify-center w-8 h-8 bg-club-blue text-white rounded-full text-sm font-bold">
                                {{ $activityCount }}
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                activité{{ $activityCount > 1 ? 's' : '' }}
                            </div>
                        @else
                            <div class="text-gray-400 text-xs py-2">
                                Repos
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Vue détaillée -->
    <div x-show="showDetails" x-transition>
        <div class="grid grid-cols-7 gap-0">
            @foreach($daysOfWeek as $day)
                @php
                    $dayActivities = $activitiesByDay->get($day, collect());
                    $isToday = $day === now()->locale('fr')->dayName;
                @endphp
                
                <div class="border-r border-b last:border-r-0 min-h-32 p-3 @if($isToday) bg-club-yellow/10 @endif">
                    <!-- En-tête du jour -->
                    <div class="text-center mb-3">
                        <div class="text-sm font-semibold @if($isToday) text-club-blue @else text-gray-700 @endif">
                            {{ $day }}
                        </div>
                        @if($isToday)
                            <div class="w-2 h-2 bg-club-yellow rounded-full mx-auto mt-1"></div>
                        @endif
                    </div>
                    
                    <!-- Activités du jour -->
                    <div class="space-y-1">
                        @forelse($dayActivities as $activity)
                            <div class="bg-club-blue/10 text-club-blue text-xs p-2 rounded border-l-2 border-club-blue">
                                <div class="font-medium truncate">{{ $activity['activity'] }}</div>
                                @if(isset($activity['time']))
                                    <div class="text-gray-600 mt-1">{{ $activity['time'] }}</div>
                                @endif
                                @if(isset($activity['level']))
                                    <div class="mt-1">
                                        <span class="inline-block px-1 py-0.5 text-xs rounded
                                            @if($activity['level'] === 'Tous niveaux') bg-blue-100 text-blue-700
                                            @elseif($activity['level'] === 'Débutant') bg-green-100 text-green-700
                                            @elseif($activity['level'] === 'Débutant+') bg-yellow-100 text-yellow-700
                                            @elseif($activity['level'] === 'Débutant+ / Confirmé') bg-pink-100 text-pink-700
                                            @else bg-red-100 text-red-700 @endif">
                                            {{ $activity['level'] }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-gray-400 text-xs text-center py-2">
                                Repos
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Footer avec statistiques -->
    <div class="bg-gray-50 px-6 py-4 border-t">
        <div class="flex justify-between items-center text-sm">
            <div class="text-gray-600">
                @php
                    $totalActivities = collect($schedules)->count();
                    $activeDays = $activitiesByDay->count();
                @endphp
                <span class="font-semibold text-club-blue">{{ $totalActivities }}</span> activités sur 
                <span class="font-semibold text-club-blue">{{ $activeDays }}</span> jours
            </div>
            <div class="flex items-center space-x-4 text-xs text-gray-500">
                <div class="flex items-center space-x-1">
                    <div class="w-2 h-2 bg-club-blue rounded-full"></div>
                    <span>Activités</span>
                </div>
                <div class="flex items-center space-x-1">
                    <div class="w-2 h-2 bg-club-yellow rounded-full"></div>
                    <span>Aujourd'hui</span>
                </div>
            </div>
        </div>
    </div>
</div>
