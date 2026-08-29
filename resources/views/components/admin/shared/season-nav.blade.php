@props([
    'model',
    'options' => [],
    'label' => __('Season'),
    'optionLabel' => 'name',
    'optionValue' => 'id',
])

{{--
    The season, as navigation — not as a filter.

    DS-A (validated 2026-08-08): a criterion that determines *what the page is
    about* — exactly one value, never empty, and the page renders nothing
    without it — is navigation. It stays visible above the content and its
    label titles what follows. A criterion that *narrows a set* is a filter: it
    belongs in the drawer, and it is removable from a chip.

    The schedule and the training packs used to hide their season in the filter
    drawer while telling the reader to "select a season" — an instruction
    pointing at a control nothing on screen announced.

    Screens where the season genuinely narrows a set (captain-selection) keep it
    in the drawer on purpose. Do not "fix" those.
--}}
<div {{ $attributes->class(['mb-4 flex flex-wrap items-center gap-x-3 gap-y-2']) }}>
    <span class="text-xs font-bold uppercase tracking-widest text-muted">{{ $label }}</span>

    <x-select
        :options="$options"
        :option-label="$optionLabel"
        :option-value="$optionValue"
        wire:model.live="{{ $model }}"
        :placeholder="__('Select a season')"
        class="select-sm w-56 font-semibold" />
</div>
