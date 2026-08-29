@props([
    'label' => null,
    'icon' => null,
    'link' => null,
    'wireClick' => null,
    'spinner' => false,
])

{{-- @slot note — why an action the reader might look for is not in the list. --}}

{{--
    One named primary action in the row, everything else behind a named menu.

    Row actions used to be bare icons carrying a tooltip: no screen reader
    announces it and no thumb can reach it. Spelling every action out instead
    makes the row overflow, so only the action the reader came for stays inline.

    The panel is one piece of markup laid out two ways. On a phone it is a bottom
    sheet — a dropdown anchored to a button at the right edge of a 375px screen
    has nowhere to go. From lg it is an ordinary dropdown.

    The slot takes <x-menu-item> entries, which are labelled by contract.
--}}
<div
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    class="relative flex items-center justify-end gap-1.5"
>
    {{-- No label means the reader may not take the primary action: the row then
    carries only what they are allowed to do. --}}
    @if (filled($label))
        <x-button
            data-row-menu-primary
            :label="$label"
            :icon="$icon"
            :link="$link"
            :wire:click="$wireClick"
            :spinner="$spinner"
            class="btn-xs btn-outline" />
    @endif

    @if ($slot->isNotEmpty())
        <button
            type="button"
            data-row-menu-trigger
            @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-label="{{ __('More actions for this row') }}"
            class="btn btn-xs btn-ghost"
        >
            {{ __('More') }}
            <x-icon name="o-chevron-down" class="h-3.5 w-3.5" />
        </button>

        {{-- Scrim: phone only, so a tap outside the sheet closes it. --}}
        <div
            x-show="open"
            x-cloak
            @click="open = false"
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
        ></div>

        <div
            x-show="open"
            x-cloak
            @click.outside="open = false"
            data-row-menu-panel
            role="menu"
            class="fixed inset-x-0 bottom-0 z-50 max-h-[70vh] overflow-y-auto rounded-t-2xl border-t border-base-300 bg-base-100 pb-[env(safe-area-inset-bottom)] shadow-2xl
                   lg:absolute lg:inset-x-auto lg:right-0 lg:top-full lg:bottom-auto lg:mt-1 lg:w-64 lg:rounded-xl lg:border lg:shadow-lg"
        >
            <div class="px-4 pt-3 pb-1 text-sm font-semibold lg:hidden">{{ __('Choose an action') }}</div>

            {{-- 44px is the Apple HIG comfort target; daisyUI's menu rows come in at 38.
            The sheet is the one place a thumb operates, so it gets the taller rows. --}}
            <ul class="menu w-full [&_li>*]:min-h-11 lg:[&_li>*]:min-h-9">
                {{ $slot }}
            </ul>

            @isset($note)
                {{-- An action that cannot be taken is removed and explained, never greyed
                out in silence: the reason used to live in a tooltip, which is nowhere at
                all under a thumb. --}}
                <p class="border-t border-base-300 bg-warning/10 px-4 py-3 text-xs text-warning-content">
                    {{ $note }}
                </p>
            @endisset

            <button
                type="button"
                @click="open = false"
                class="w-full border-t border-base-300 px-4 py-3 text-sm text-base-content/70 lg:hidden"
            >{{ __('Cancel') }}</button>
        </div>
    @endif
</div>
