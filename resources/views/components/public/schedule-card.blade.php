@props(['schedule', 'index' => 0])

@php
    $type = $schedule['type'] ?? null;

    [$borderClass, $typeBadgeClass, $typeLabel] = match ($type) {
        'Directed'   => ['border-l-4 border-blue-500',  'bg-blue-50 text-blue-700',   'Dirigé'],
        'Free'       => ['border-l-4 border-gray-300',  'bg-gray-100 text-gray-500',  'Libre'],
        'Supervised' => ['border-l-4 border-amber-400', 'bg-amber-50 text-amber-700', 'Supervisé'],
        'match'      => ['border-l-4 border-red-400',   'bg-red-50 text-red-600',     'Interclubs'],
        default      => ['border-l-4 border-gray-200',  'bg-gray-100 text-gray-500',  ''],
    };

    $levelBadgeClass = match ($schedule['level'] ?? null) {
        'Tous niveaux'   => 'bg-blue-100 text-blue-700',
        'Débutant'       => 'bg-green-100 text-green-700',
        'Jeunes'         => 'bg-green-100 text-green-700',
        'Confirmé'       => 'bg-orange-100 text-orange-700',
        'Compétition'    => 'bg-red-100 text-red-700',
        'Jeunes espoirs' => 'bg-purple-100 text-purple-700',
        default          => 'bg-gray-100 text-gray-600',
    };
@endphp

<div class="bg-white rounded-lg border border-gray-200 {{ $borderClass }} hover:shadow-md transition-shadow duration-200 animate-on-scroll"
     style="animation-delay: {{ $index * 0.05 }}s">
    <div class="p-5">
        <div class="flex items-start justify-between gap-4">

            {{-- Left: activity info --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                    @if($typeLabel)
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $typeBadgeClass }}">
                            {{ $typeLabel }}
                        </span>
                    @endif
                    <span class="font-bold text-gray-900 text-base leading-tight">{{ $schedule['activity'] }}</span>
                </div>

                <div class="flex items-center gap-x-3 gap-y-1 flex-wrap text-sm text-gray-500">
                    <span class="font-medium text-gray-700">{{ $schedule['time'] }}</span>

                    @if(!empty($schedule['location']))
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ $schedule['location'] }}
                        </span>
                    @endif

                    @if(!empty($schedule['coach']))
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ $schedule['coach'] }}
                        </span>
                    @endif
                </div>

                @if(!empty($schedule['description']))
                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">{{ $schedule['description'] }}</p>
                @endif
            </div>

            {{-- Right: level badge only --}}
            @if(!empty($schedule['level']))
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full {{ $levelBadgeClass }} shrink-0 self-start">
                    {{ $schedule['level'] }}
                </span>
            @endif
        </div>
    </div>
</div>
