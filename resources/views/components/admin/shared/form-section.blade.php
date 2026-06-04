@props(['title', 'subtitle', 'separator' => true])
<div class="col-span-6 md:col-span-2">
    <x-header title="{{ $title }}" subtitle="{{ $subtitle }}" size="md" />
</div>

<div class="col-span-6 md:col-span-4 grid gap-2">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{ $slot }}
    </div>
</div>

@if ($separator === true)
    <div class="col-span-6">
        <x-menu-separator />
    </div>
@endif
