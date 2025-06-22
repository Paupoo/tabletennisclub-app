@props(['schedules' => [], 'compact' => false])

@php
    // Version compacte pour sidebar ou widgets
    $daysOfWeek = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
    $fullDays = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="@if($compact) p-4 @else p-6 @endif bg-white rounded-lg border my-6">
    @if(!$compact)
        <h4 class="text-lg font-semibold text-gray-900 mb-4 text-center">Cette Semaine</h4>
    @endif
    
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
