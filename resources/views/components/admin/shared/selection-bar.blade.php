{{--
    Full-width sticky bottom selection bar.
    Used by Proposals A (Command Bar) and B (Compact + Overflow).

    Props:
    - selected            array   — currently selected IDs
    - total               int     — total records matching current filters
    - selectingAllResults bool    — whether "all results" mode is active
    - selectAll           bool    — whether current-page selectAll is checked
    - actions             slot    — bulk action buttons (varies by proposal)
--}}
@props([
    'selected'            => [],
    'total'               => 0,
    'selectingAllResults' => false,
    'selectAll'           => false,
])

@if (count($selected) > 0)
    <div
        class="fixed inset-x-0 bottom-0 z-50 border-t border-base-300 bg-base-100 shadow-[0_-4px_24px_rgba(0,0,0,0.08)]"
        x-data
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0">

        {{-- Gmail select-all banner --}}
        @if ($selectAll && ! $selectingAllResults && $total > count($selected))
            <div class="border-b border-primary/10 bg-primary/5 px-5 py-2 text-center text-sm">
                {{ __(':count items on this page are selected.', ['count' => count($selected)]) }}
                <button wire:click="selectAllResults"
                    class="ml-1 font-semibold text-primary underline-offset-2 hover:underline">
                    {{ __('Select all :total results', ['total' => $total]) }}
                </button>
            </div>
        @elseif ($selectingAllResults)
            <div class="border-b border-primary/10 bg-primary/5 px-5 py-2 text-center text-sm font-medium text-primary">
                {{ __('All :total results are selected.', ['total' => $total]) }}
                <button wire:click="clearSelection"
                    class="ml-1 underline-offset-2 hover:underline">
                    {{ __('Clear selection') }}
                </button>
            </div>
        @endif

        {{-- Action bar --}}
        <div class="mx-auto flex max-w-screen-2xl items-center gap-3 px-4 py-3 md:px-6">
            <span class="shrink-0 border-r border-base-300 pr-3 text-sm font-semibold text-base-content/80">
                {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            </span>

            <div class="flex flex-1 flex-wrap items-center gap-2">
                {{ $actions }}
            </div>

            <x-button
                class="btn-ghost btn-sm btn-circle ml-auto shrink-0"
                icon="o-x-mark"
                :tooltip="__('Clear selection')"
                wire:click="clearSelection" />
        </div>
    </div>

    {{-- Spacer to prevent content hiding behind the sticky bar --}}
    <div class="h-20"></div>
@endif
