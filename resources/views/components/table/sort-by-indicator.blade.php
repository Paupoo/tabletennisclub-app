@props([
    'sortByField' => '',
    'sortDirection' => 'asc',
    'currentName' => '',
])

@if ($sortByField === $currentName)
    @if ($sortDirection === 'asc')
        <x-ui.icon name="bars-arrow-down" class="w-2 h-2 text-gray-600" />
    @else
        <x-ui.icon name="bars-arrow-up" class="w-2 h-2 text-gray-600" />
    @endif
@else
    <x-ui.icon name="chevron-up-down" class="w-2 h-2 text-gray-200" />
@endif
