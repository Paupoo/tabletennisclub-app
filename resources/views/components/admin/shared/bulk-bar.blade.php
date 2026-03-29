@props(['selected' => []])

@if (count($selected) > 0)
    <div class="border-base-200 bg-base-100 mb-6 flex flex-wrap items-center gap-3 rounded-xl border px-5 py-3" x-data
        x-transition:enter-end="opacity-100 translate-y-0" x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter="transition ease-out duration-150">
        <span class="text-base-content/70 border-base-200 border-r pr-3 text-sm font-medium">
            {{ trans_choice('usersSelectedCount', count($selected), ['count' => count($selected)]) }}
        </span>

        {{ $actions }}

        <x-button class="btn-ghost btn-sm btn-square ml-auto" icon="o-x-mark" tooltip="{{ __('Clear selection') }}"
            wire:click="$set('selected', [])" />
    </div>
@endif
