{{--
    The badge of a responsible adult on file twice, naming whom they double.

    Merging happens from a child's file; the name tells the office which two
    sheets it is about before they go there.
--}}
@props(['counterpart'])

@if ($counterpart !== null)
    <div {{ $attributes->class('flex flex-wrap items-center gap-1.5') }}>
        <x-badge :value="__('On file twice')" class="badge-warning badge-soft badge-sm shrink-0" />
        <span class="text-xs text-muted">{{ __('Same as :name', ['name' => $counterpart->full_name]) }}</span>
    </div>
@endif
