@props([
    'icon' => 'o-inbox',
    'heading',
    'filtered' => false,
    'filteredHeading' => null,
    'createLabel' => null,
    'createHref' => null,
])

{{--
    An empty list means one of two things, and the reader cannot tell them apart:
    nothing exists yet, or the filters exclude everything. Saying "adjust your
    filters" in both cases is absurd advice for a club opening its first season.

    So the state carries the action that matches its cause — clear the filters, or
    create the first record — instead of describing the absence and stopping.
--}}
@if ($filtered)
    <x-empty-state
        :icon="$icon"
        :heading="$filteredHeading ?? $heading"
        :message="__('No record matches the current filters.')">
        <x-button
            class="btn-outline btn-sm"
            icon="o-x-mark"
            :label="__('Clear filters')"
            wire:click="clearFilters" />
    </x-empty-state>
@else
    <x-empty-state
        :icon="$icon"
        :heading="$heading"
        :message="$slot->isNotEmpty() ? null : __('Nothing here yet.')"
        :buttonText="$createHref ? $createLabel : null"
        :href="$createHref" />
@endif
