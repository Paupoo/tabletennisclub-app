@props(['title' => __('Filters')])

<x-drawer wire:model="filterDrawer" :title="$title" right class="w-full max-w-xs">
    <div class="space-y-5 p-1">
        {{ $filters }}
    </div>
    <x-slot:actions>
        <x-button :label="__('Close')" wire:click="$set('filterDrawer', false)" />
        <x-button class="btn-ghost" icon="o-x-mark" :label="__('Clear filters')" wire:click="clearFilters" />
    </x-slot:actions>
</x-drawer>
