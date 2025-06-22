@props(['schedules' => [], 'compact' => false])

@php
    // Version compacte pour sidebar ou widgets
    $daysOfWeek = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    $fullDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="@if($compact) p-4 @else p-6 @endif bg-white rounded-lg border" x-data="{ showDetails: false }">
    <div class="flex items-center justify-between mb-4">
        @if(!$compact)
            <h4 class="text-lg font-semibold text-gray-900">Cette Semaine</h4>
        @else
            <h4 class="text-sm font-medium text-gray-700">Semaine</h4>
        @endif
        
        <!-- Toggle Switch -->
        <div class="flex items-center space-x-2">
            <span class="text-xs text-gray-500" :class="{ 'text-gray-400': showDetails }">Aperçu</span>
            <button @click="showDetails = !showDetails" 
                    class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-club-blue focus:ring-offset-2"
                    :class="showDetails ? 'bg-club-blue' : 'bg-gray-200'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                      :class="showDetails ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
            <span class="text-xs text-gray-500" :class="{ 'text-club-blue font-medium': showDetails }">Détails</span>
        </div>
    </div>
    
    <!-- Vue d'ensemble -->
    <div x-show="!showDetails" x-transition>
        <div class="flex justify-center space-x-1">
            @foreach($fullDays as $index => $fullDay)
                @php
                    $dayActivities = $activitiesByDay->get($fullDay, collect());
                    $hasActivities = $dayActivities->isNotEmpty();
                    $isToday = $fullDay === now()->locale('fr')->dayName;
                @endphp
                
                <div class="flex flex-col items-center">
                    <div class="text-xs text-gray-500 mb-1">{{ $daysOfWeek[$index] }}</div>
                    <div class="@if($compact) w-6 h-6 @else w-8 h-8 @endif rounded-full border flex items-center justify-center
                        @if($hasActivities) 
                            @if($isToday) bg-club-yellow border-club-yellow @else bg-club-blue border-club-blue @endif
                        @else 
                            border-gray-200 bg-gray-50 
                        @endif">
                        @if($hasActivities)
                            <span class="@if($isToday) text-club-blue @else text-white @endif text-xs font-bold">
                                {{ $dayActivities->count() }}
                            </span>
                        @else
                            <span class="text-gray-300 text-xs">—</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        
        @if(!$compact)
            <div class="mt-3 text-center text-xs text-gray-500">
                {{ collect($schedules)->count() }} activités cette semaine
            </div>
        @endif
    </div>
    
    <!-- Vue détaillée -->
    <div x-show="showDetails" x-transition class="space-y-3">
        @foreach($schedules as $index => $schedule)
            <x-schedule-card :schedule="$schedule" :index="$index" />
        @endforeach
    </div>
</div>
