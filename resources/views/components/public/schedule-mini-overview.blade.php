@props(['schedules' => [], 'compact' => false])

@php
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="{{ $compact ? 'p-4' : 'p-0' }} bg-white rounded-2xl shadow-sm border overflow-hidden" x-data="{ showDetails: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div>
            <h4 class="{{ $compact ? 'text-sm font-semibold' : 'font-bold' }} text-gray-900">
                Planning hebdomadaire
            </h4>
            @if(!$compact)
                <p class="text-xs text-gray-400 mt-0.5">
                    @php $count = collect($schedules)->count(); @endphp
                    {{ $count }} activité{{ $count > 1 ? 's' : '' }} cette semaine
                </p>
            @endif
        </div>

        {{-- Segmented toggle --}}
        <div class="inline-flex bg-gray-100 rounded-lg p-1 gap-0.5 shrink-0">
            <button @click="showDetails = false"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all duration-150 cursor-pointer"
                    :class="showDetails ? 'text-gray-400 hover:text-gray-600' : 'bg-white text-gray-900 shadow-sm'">
                Aperçu
            </button>
            <button @click="showDetails = true"
                    class="px-3 py-1 text-xs font-medium rounded-md transition-all duration-150 cursor-pointer"
                    :class="!showDetails ? 'text-gray-400 hover:text-gray-600' : 'bg-white text-gray-900 shadow-sm'">
                Détails
            </button>
        </div>
    </div>

    {{-- Aperçu : liste compacte --}}
    <div x-show="!showDetails" x-transition class="px-5 py-4">
        @if($activitiesByDay->isEmpty())
            <p class="text-sm text-gray-400 text-center py-2">Aucune activité programmée.</p>
        @else
            <div class="space-y-3.5">
                @foreach($activitiesByDay as $day => $activities)
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xs font-bold uppercase tracking-widest text-gray-400">{{ $day }}</span>
                            <div class="flex-1 h-px bg-gray-100"></div>
                        </div>
                        <div class="space-y-1.5">
                            @foreach($activities as $activity)
                                @php
                                    $dotColor = match ($activity['type'] ?? null) {
                                        'Directed'   => 'bg-blue-500',
                                        'Free'       => 'bg-gray-300',
                                        'Supervised' => 'bg-amber-400',
                                        'match'      => 'bg-red-400',
                                        default      => 'bg-gray-300',
                                    };
                                @endphp
                                <div class="flex items-center gap-2 pl-1">
                                    <div class="w-2 h-2 rounded-full {{ $dotColor }} shrink-0"></div>
                                    <span class="text-sm text-gray-700 flex-1 min-w-0">{{ $activity['activity'] }}</span>
                                    <span class="text-xs text-gray-400 whitespace-nowrap shrink-0">
                                        {{ explode(' – ', $activity['time'])[0] }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Détails : schedule cards --}}
    <div x-show="showDetails" x-transition class="px-5 py-4 space-y-3">
        @forelse($schedules as $index => $schedule)
            <x-public.schedule-card :schedule="$schedule" :index="$index" />
        @empty
            <p class="text-sm text-gray-400 text-center py-2">Aucune activité programmée.</p>
        @endforelse
    </div>
</div>
