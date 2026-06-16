@props(['schedule', 'index' => 0])

@php
    $type = $schedule['type'] ?? null;

    [$borderClass, $typeBadgeTone, $typeLabel] = match ($type) {
        'Directed'   => ['border-l-4 border-blue-500',  'info',    'Dirigé'],
        'Free'       => ['border-l-4 border-gray-300',  'neutral', 'Libre'],
        'Supervised' => ['border-l-4 border-amber-400', 'warning', 'Supervisé'],
        'match'      => ['border-l-4 border-red-400',   'danger',  'Interclubs'],
        default      => ['border-l-4 border-gray-200',  'neutral', ''],
    };

    $levelBadgeTone = match ($schedule['level'] ?? null) {
        'Tous niveaux'   => 'primary',
        'Débutant'       => 'success',
        'Jeunes'         => 'success',
        'Confirmé'       => 'orange',
        'Compétition'    => 'danger',
        'Jeunes espoirs' => 'purple',
        default          => 'neutral',
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
                        <x-badge :tone="$typeBadgeTone" size="xs">{{ $typeLabel }}</x-badge>
                    @endif
                    <span class="font-bold text-gray-900 text-base leading-tight">{{ $schedule['activity'] }}</span>
                </div>

                <div class="flex items-center gap-x-3 gap-y-1 flex-wrap text-sm text-gray-500">
                    <span class="font-medium text-gray-700">{{ $schedule['time'] }}</span>

                    @if(!empty($schedule['location']))
                        <span class="flex items-center gap-1">
                            <x-icon name="o-map-pin" class="w-3.5 h-3.5 shrink-0" />
                            {{ $schedule['location'] }}
                        </span>
                    @endif

                    @if(!empty($schedule['coach']))
                        <span class="flex items-center gap-1">
                            <x-icon name="o-user" class="w-3.5 h-3.5 shrink-0" />
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
                <x-badge :tone="$levelBadgeTone" size="xs" class="shrink-0 self-start">{{ $schedule['level'] }}</x-badge>
            @endif
        </div>
    </div>
</div>
