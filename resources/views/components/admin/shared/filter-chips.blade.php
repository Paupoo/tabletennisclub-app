@props(['chips' => []])

@if (count($chips) > 0)
    <div class="mb-3 flex flex-wrap items-center gap-2" x-data
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0">
        @foreach ($chips as $chip)
            <span class="badge badge-soft badge-primary inline-flex items-center gap-1 py-2.5 pl-3 pr-2 text-sm">
                {{ $chip['label'] }}
                {{-- 24x24 at least (WCAG 2.5.8): the members list opens with a chip, so
                     this cross is reached with a thumb on the first screen of a phone. --}}
                <button
                    type="button"
                    wire:click="removeFilter('{{ $chip['key'] }}')"
                    class="inline-flex h-6 w-6 items-center justify-center rounded-full transition-colors hover:bg-primary/20"
                    title="{{ __('Remove filter') }}"
                    aria-label="{{ __('Remove filter') }}">
                    <x-icon name="o-x-mark" class="h-3 w-3" />
                </button>
            </span>
        @endforeach
        <button
            type="button"
            wire:click="clearFilters"
            class="inline-flex min-h-6 items-center px-1 text-xs text-base-content/60 underline-offset-2 transition-colors hover:text-base-content hover:underline">
            {{ __('Clear all') }}
        </button>
    </div>
@endif
