@props([
    'total_tables' => null,
])


<x-badge class="badge-outline">
    <x-icon name="o-square-3-stack-3d" class="w-3.5 h-3.5 -mr-1" />
    <span class="font-medium text-xs">
        {{ $total_tables }} {{ __('tables')  }}
    </span>
</x-badge>
