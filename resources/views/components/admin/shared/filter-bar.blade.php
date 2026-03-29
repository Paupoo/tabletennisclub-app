@props([
    'show' => false,
    'activeFiltersCount' => 0,
])

@if ($show)
    <div class="border-base-300 bg-base-100 mb-3 grid grid-cols-1 gap-4 rounded-xl border p-4 shadow-sm md:grid-cols-3"
        x-data x-transition:enter-end="opacity-100 translate-y-0" x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter="transition ease-out duration-150">
        {{ $filters }}

        <div class="border-base-200 col-span-full flex justify-end border-t pt-3">
            <x-button class="btn-ghost btn-sm" icon="o-eye-slash" label="{{ __('Hide filters') }}"
                wire:click="$toggle('showFilters')" />
            <x-button class="btn-ghost btn-sm" icon="o-x-mark" label="{{ __('Clear filters') }}"
                wire:click="resetFilters" />
        </div>
    </div>
@endif
