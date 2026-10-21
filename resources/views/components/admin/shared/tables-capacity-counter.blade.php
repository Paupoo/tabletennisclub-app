{{-- Ce qu'une salle offre vraiment, par usage : la place saisie, ramenée aux tables jouables. --}}
@props(['room'])

@php
    $usages = [
        ['icon' => 'o-academic-cap', 'label' => __('Training capacity'), 'usable' => $room->usableTrainingCapacity(), 'capacity' => $room->capacity_for_trainings],
        ['icon' => 'o-trophy', 'label' => __('Matches capacity'), 'usable' => $room->usableMatchCapacity(), 'capacity' => $room->capacity_for_interclubs],
        ['icon' => 'o-flag', 'label' => __('Tournament capacity'), 'usable' => $room->usableTournamentCapacity(), 'capacity' => $room->capacity_for_tournaments],
    ];
@endphp

<x-badge class="badge-outline gap-2">
    <x-slot:value>
        <span class="font-medium text-xs">{{ __('Room capacity:') }}</span>
        @foreach ($usages as $usage)
            <span class="inline-flex items-center gap-0.5 text-xs font-medium" title="{{ $usage['label'] }}">
                <x-icon :name="$usage['icon']" class="h-3.5 w-3.5" />
                @if ($usage['usable'] < $usage['capacity'])
                    <span class="text-warning-content">{{ __(':usable of :capacity', ['usable' => $usage['usable'], 'capacity' => $usage['capacity']]) }}</span>
                @else
                    {{ $usage['usable'] }}
                @endif
            </span>
        @endforeach
    </x-slot:value>
</x-badge>
