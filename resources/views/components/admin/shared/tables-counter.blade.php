{{--
    Badge « N tables » d'une salle.

    `count` est toujours un comptage vivant (withCount). La salle n'a plus de
    colonne dénormalisée : elle avait dérivé, et rien ne la relisait.
--}}
@props([
    'count' => 0,
])

<x-badge class="badge-outline">
    <x-icon name="o-square-3-stack-3d" class="w-3.5 h-3.5 -mr-1" />
    <span class="font-medium text-xs">
        {{ $count }} {{ __('tables') }}
    </span>
</x-badge>
