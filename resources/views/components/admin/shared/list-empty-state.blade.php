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
    {{-- Le slot sert de message. Il ne peut pas être passé tel quel à
    <x-empty-state>, qui rend son propre slot *à la place* du bouton : une
    explication ferait alors disparaître l'action. --}}
    <x-empty-state
        :icon="$icon"
        :heading="$heading"
        :message="$slot->isNotEmpty() ? trim((string) $slot) : __('Nothing here yet.')"
        :buttonText="$createHref ? $createLabel : null"
        :href="$createHref" />
@endif
