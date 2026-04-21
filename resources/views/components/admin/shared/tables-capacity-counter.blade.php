@props([
    'training_capacity' => null,
    'interclub_capacity' => null,
])


<x-badge class="badge-outline">
    <div class="font-medium text-xs"> {{ __('Room capacity:') }} </div>
    <x-icon name="o-academic-cap" class="w-3.5 h-3.5" />
    <span class="font-medium text-xs">
        {{ $training_capacity }}
    </span>
    <x-icon name="o-trophy" class="w-3.5 h-3.5" />
    <span class="font-medium text-xs">
        {{ $interclub_capacity }}
    </span>
</x-badge>
