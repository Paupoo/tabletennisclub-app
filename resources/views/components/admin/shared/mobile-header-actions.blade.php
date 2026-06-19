@props([
    'filterCount' => 0,
    'showSearch' => true,
    'showMore' => true,
])

<div class="flex items-center gap-1 lg:hidden">
    @if ($showSearch)
        <button class="btn btn-ghost btn-circle btn-sm" @click="mobileSearchOpen = true">
            <x-icon name="o-magnifying-glass" class="h-5 w-5" />
        </button>
    @endif
    <button class="btn btn-ghost btn-circle btn-sm relative {{ $filterCount > 0 ? 'btn-active' : '' }}"
        wire:click="$set('filterDrawer', true)">
        <x-icon name="o-funnel" class="h-5 w-5" />
        @if ($filterCount > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-xs font-bold leading-none text-primary-content">{{ $filterCount }}</span>
        @endif
    </button>
    @if ($showMore)
        <button class="btn btn-primary btn-circle btn-sm" @click="mobileActionsOpen = true">
            <x-icon name="o-bars-3" class="h-5 w-5" />
        </button>
    @endif
</div>
