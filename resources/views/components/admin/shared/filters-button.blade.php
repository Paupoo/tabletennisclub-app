@props([
    'count' => 0,
])

<x-button class="btn-ghost {{ $count > 0 ? 'btn-active' : '' }}"
    icon="o-funnel" :label="__('Filters')"
    wire:click="$set('filterDrawer', true)" {{ $attributes }}>
    @if ($count > 0)
        <x-badge class="badge-sm badge-primary" value="{{ $count }}" />
    @endif
</x-button>
