@props(['schedules' => []])

@php
    $activitiesByDay = collect($schedules)->groupBy('day');
@endphp

<div class="bg-white rounded-2xl shadow-sm border overflow-hidden" x-data="{ showDetails: false }">

    {{-- Header --}}
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
            <h3 class="font-bold text-gray-900">Planning hebdomadaire</h3>
            <p class="text-sm text-gray-400 mt-0.5">
                @php $count = collect($schedules)->count(); $days = $activitiesByDay->count(); @endphp
                {{ $count }} activité{{ $count > 1 ? 's' : '' }} · {{ $days }} jour{{ $days > 1 ? 's' : '' }}
            </p>
        </div>

        {{-- Segmented toggle --}}
        <div class="inline-flex bg-gray-100 rounded-lg p-1 gap-0.5 shrink-0">
            <button @click="showDetails = false"
                    class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150 cursor-pointer"
                    :class="showDetails ? 'text-gray-400 hover:text-gray-600' : 'bg-white text-gray-900 shadow-sm'">
                Aperçu
            </button>
            <button @click="showDetails = true"
                    class="px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-150 cursor-pointer"
                    :class="!showDetails ? 'text-gray-400 hover:text-gray-600' : 'bg-white text-gray-900 shadow-sm'">
                Détails
            </button>
        </div>
    </div>

    {{-- Aperçu : liste compacte jour par jour --}}
    <div x-show="!showDetails" x-transition class="px-6 py-5">
        @if($activitiesByDay->isEmpty())
            <p class="text-sm text-gray-400 text-center py-4">Aucune activité programmée.</p>
        @else
            <div class="space-y-4">
                @foreach($activitiesByDay as $day => $activities)
                    <div>
                        <div class="flex items-center gap-2 mb-2">
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
                                <div class="flex items-center gap-2.5 pl-1">
                                    <div class="w-2 h-2 rounded-full {{ $dotColor }} shrink-0"></div>
                                    <span class="text-sm font-medium text-gray-800 flex-1 min-w-0">{{ $activity['activity'] }}</span>
                                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $activity['time'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-4 mt-5 pt-4 border-t border-gray-100 text-xs text-gray-400 flex-wrap">
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div> Dirigé</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-amber-400"></div> Supervisé</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-gray-300"></div> Libre</div>
            <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-red-400"></div> Interclubs</div>
        </div>
    </div>

    {{-- Détails : schedule cards --}}
    <div x-show="showDetails" x-transition class="px-6 py-5 space-y-3">
        @forelse($schedules as $index => $schedule)
            <x-public.schedule-card :schedule="$schedule" :index="$index" />
        @empty
            <p class="text-sm text-gray-400 text-center py-4">Aucune activité programmée.</p>
        @endforelse
    </div>
</div>
