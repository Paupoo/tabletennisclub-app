@props(['hidden'])

{{--
    Le bouton qui déplie le reste d'une liste raccourcie. À placer dans le
    conteneur qui porte `x-data="{ expanded: false }"`.
--}}
<div class="pt-2 text-center">
    <button type="button" class="btn btn-ghost btn-sm cursor-pointer" x-on:click="expanded = ! expanded">
        <span x-show="! expanded">{{ __('Show :count more', ['count' => $hidden]) }}</span>
        <span x-show="expanded" x-cloak>{{ __('Show fewer') }}</span>
        <x-icon name="o-chevron-down" class="h-4 w-4 transition-transform" x-bind:class="expanded && 'rotate-180'" />
    </button>
</div>
