@props(['title', 'subtitle', 'separator' => true])

{{--
    The section owns its two-column layout instead of borrowing one. It used to
    emit bare col-span-* children and rely on an ancestor grid: the settings page
    was once wrapped in <x-form>, whose plain `grid` created the implicit tracks
    those spans needed. That wrapper went away in 173af8e5 and every section has
    stacked since, on both pages using this component.
--}}
<div class="grid grid-cols-1 gap-x-8 gap-y-4 md:grid-cols-6">
    <div class="md:col-span-2">
        <x-header :title="$title" :subtitle="$subtitle" size="md" />
    </div>

    <div class="grid gap-2 md:col-span-4">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{ $slot }}
        </div>
    </div>
</div>

@if ($separator === true)
    <x-menu-separator />
@endif
