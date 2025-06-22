@props(['schedules' => []])

@php
    $daysOfWeek = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="bg-white rounded-2xl shadow-sm border overflow-hidden my-6">
    <!-- En-tête -->
    <div class="bg-gradient-to-r from-club-blue to-club-blue-light text-white p-6">
        <h3 class="text-xl font-bold text-center">Planning Hebdomadaire</h3>
    </div>
    
    <!-- Grille des jours -->
    <div class="grid grid-cols-7 gap-0">
        @foreach($daysOfWeek as $day)
            @php
                $dayActivities = $activitiesByDay->get($day, collect());
                $isToday = $day === now()->locale('fr')->dayName;
            @endphp
            
            <div class="border-r border-b last:border-r-0 min-h-32 p-3 @if($isToday) bg-club-yellow/10 @endif">
                <!-- En-tête du jour -->
                <div class="text-center mb-2">
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
