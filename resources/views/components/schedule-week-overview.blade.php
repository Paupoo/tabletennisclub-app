@props(['schedules' => []])

@php
    // Jours de la semaine en français
    $daysOfWeek = [
        'Lundi' => 'L',
        'Mardi' => 'M', 
        'Mercredi' => 'M',
        'Jeudi' => 'J',
        'Vendredi' => 'V',
        'Samedi' => 'S',
        'Dimanche' => 'D'
    ];
    
    // Grouper les activités par jour
    $activitiesByDay = collect($schedules)->groupBy('day');
    
    // Couleurs selon le type d'activité
    $activityColors = [
        'Entraînement Libre' => 'bg-blue-500',
        'École de Tennis de Table' => 'bg-green-500',
        'Championnat Interne' => 'bg-red-500',
        'Perfectionnement Technique' => 'bg-yellow-500',
        'Entraînement Jeunes' => 'bg-purple-500',
        'Tournoi Mensuel' => 'bg-pink-500',
        'default' => 'bg-club-blue'
    ];
@endphp

<div class="bg-white rounded-2xl shadow-sm border p-6 mb-8 animate-on-scroll my-8">
    <div class="text-center mb-6">
        <h3 class="text-xl font-bold text-gray-900 mb-2">Aperçu de la Semaine</h3>
        <p class="text-gray-600 text-sm">Activités programmées par jour</p>
    </div>
    
    <!-- Vue d'ensemble des jours -->
    <div class="flex justify-center mb-6">
        <div class="flex items-center space-x-2 sm:space-x-4 lg:space-x-6">
            @foreach($daysOfWeek as $fullDay => $shortDay)
                @php
                    $dayActivities = $activitiesByDay->get($fullDay, collect());
                    $hasActivities = $dayActivities->isNotEmpty();
                    $activityCount = $dayActivities->count();
                @endphp
                
                <div class="flex flex-col items-center group cursor-pointer" 
                     x-data="{ showTooltip: false }"
                     @mouseenter="showTooltip = true" 
                     @mouseleave="showTooltip = false">
                    
                    <!-- Jour de la semaine -->
                    <div class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">
                        <span class="hidden sm:inline">{{ $fullDay }}</span>
                        <span class="sm:hidden">{{ $shortDay }}</span>
                    </div>
                    
                    <!-- Indicateur visuel -->
                    <div class="relative">
                        @if($hasActivities)
                            <!-- Jour avec activités -->
                            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full border-2 border-gray-200 flex items-center justify-center relative overflow-hidden group-hover:scale-110 transition-transform duration-200">
                                @if($activityCount === 1)
                                    <!-- Une seule activité -->
                                    @php
                                        $activity = $dayActivities->first();
                                        $colorClass = $activityColors[$activity['activity']] ?? $activityColors['default'];
                                    @endphp
                                    <div class="w-full h-full {{ $colorClass }} rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold text-sm">{{ $activityCount }}</span>
                                    </div>
                                @else
                                    <!-- Plusieurs activités - effet dégradé -->
                                    <div class="w-full h-full bg-gradient-to-br from-club-blue to-club-blue-light rounded-full flex items-center justify-center">
                                        <span class="text-white font-bold text-sm">{{ $activityCount }}</span>
                                    </div>
                                @endif
                                
                                <!-- Animation de pulsation pour le jour actuel -->
                                @if($fullDay === now()->locale('fr')->dayName)
                                    <div class="absolute inset-0 rounded-full bg-club-yellow opacity-30 animate-ping"></div>
                                @endif
                            </div>
                        @else
                            <!-- Jour sans activité -->
                            <div class="w-12 h-12 sm:w-14 sm:h-14 rounded-full border-2 border-gray-200 bg-gray-50 flex items-center justify-center group-hover:bg-gray-100 transition-colors duration-200">
                                <span class="text-gray-400 text-xs">—</span>
                            </div>
                        @endif
                        
                        <!-- Tooltip -->
                        <div x-show="showTooltip" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95"
                             x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 z-10">
                            <div class="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 whitespace-nowrap max-w-48">
                                @if($hasActivities)
                                    <div class="font-semibold mb-1">{{ $fullDay }}</div>
                                    @foreach($dayActivities as $activity)
                                        <div class="text-xs opacity-90">
                                            • {{ $activity['activity'] }}
                                            @if(isset($activity['time']))
                                                <span class="text-gray-300">({{ $activity['time'] }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div>{{ $fullDay }}</div>
                                    <div class="text-xs opacity-75">Aucune activité</div>
                                @endif
                                
                                <!-- Flèche du tooltip -->
                                <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Indicateur du nombre d'activités (version mobile) -->
                    @if($hasActivities && $activityCount > 1)
                        <div class="mt-1 text-xs text-gray-500 sm:hidden">
                            {{ $activityCount }} activités
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    
    <!-- Légende -->
    <div class="border-t pt-4">
        <div class="flex flex-wrap justify-center items-center gap-4 text-xs text-gray-600">
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full bg-club-blue"></div>
                <span>Activités programmées</span>
            </div>
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full border-2 border-gray-200 bg-gray-50"></div>
                <span>Pas d'activité</span>
            </div>
            @if(collect($schedules)->where('day', now()->locale('fr')->dayName)->isNotEmpty())
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 rounded-full bg-club-yellow animate-pulse"></div>
                    <span>Aujourd'hui</span>
                </div>
            @endif
        </div>
        
        <!-- Statistiques rapides -->
        <div class="mt-4 text-center">
            @php
                $totalActivities = collect($schedules)->count();
                $activeDays = $activitiesByDay->count();
            @endphp
            <p class="text-sm text-gray-500">
                <span class="font-semibold text-club-blue">{{ $totalActivities }}</span> activités réparties sur 
                <span class="font-semibold text-club-blue">{{ $activeDays }}</span> jours
            </p>
        </div>
    </div>
</div>
