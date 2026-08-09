@props([
    'filterCount' => 0,
    'showSearch' => true,
    'showMore' => true,
])

{{--
    Three icons and nothing else: a screen reader used to announce "button,
    button, button" at the top of every list in the back office. An icon is not
    a name — same rule as the row actions, which were named in b42fdef7.

    The filter button says how many filters are on, because the badge that
    carries that count is a number no one hears.
--}}
<div class="flex items-center gap-1 lg:hidden">
    @if ($showSearch)
        <button type="button" class="btn btn-ghost btn-circle btn-sm" @click="mobileSearchOpen = true"
            aria-label="{{ __('Search') }}">
            <x-icon name="o-magnifying-glass" class="h-5 w-5" />
        </button>
    @endif
    <button type="button" class="btn btn-ghost btn-circle btn-sm relative {{ $filterCount > 0 ? 'btn-active' : '' }}"
        wire:click="$set('filterDrawer', true)"
        aria-label="{{ $filterCount > 0 ? __(':count filters active', ['count' => $filterCount]) : __('Filters') }}">
        <x-icon name="o-funnel" class="h-5 w-5" />
        @if ($filterCount > 0)
            <span aria-hidden="true"
                class="absolute -top-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-xs font-bold leading-none text-primary-content">{{ $filterCount }}</span>
        @endif
    </button>
    @if ($showMore)
        <button type="button" class="btn btn-primary btn-circle btn-sm" @click="mobileActionsOpen = true"
            aria-label="{{ __('More actions') }}">
            <x-icon name="o-bars-3" class="h-5 w-5" />
        </button>
    @endif
</div>
