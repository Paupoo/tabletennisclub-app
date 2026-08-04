{{--
    Floating centered pill selection indicator.
    Used by Proposal C (Floating Pill / Notion-style).

    Props:
    - selected            array   — currently selected IDs
    - total               int     — total records matching current filters
    - selectingAllResults bool    — whether "all results" mode is active
    - selectAll           bool    — whether current-page selectAll is checked
    - actions             slot    — bulk action buttons
--}}
@props([
    'selected'            => [],
    'total'               => 0,
    'selectingAllResults' => false,
    'selectAll'           => false,
])

@if (count($selected) > 0)
    <div
        class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex flex-col items-center gap-2 px-4"
        x-data
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95 translate-y-2"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0">

        {{-- Gmail select-all banner --}}
        @if ($selectAll && ! $selectingAllResults && $total > count($selected))
            <div class="pointer-events-auto rounded-xl border border-primary/20 bg-primary/5 px-4 py-1.5 text-sm shadow">
                <button wire:click="selectAllResults"
                    class="font-semibold text-primary underline-offset-2 hover:underline">
                    {{ __('Select all :total results', ['total' => $total]) }}
                </button>
            </div>
        @elseif ($selectingAllResults)
            <div class="pointer-events-auto rounded-xl border border-primary/20 bg-primary/5 px-4 py-1.5 text-sm font-medium text-primary shadow">
                {{ __('All :total results selected', ['total' => $total]) }}
                <button wire:click="clearSelection" class="ml-1 underline-offset-2 hover:underline">
                    {{ __('Clear') }}
                </button>
            </div>
        @endif

        {{-- Pill --}}
        <div class="pointer-events-auto flex max-w-2xl flex-wrap items-center gap-2 rounded-2xl border border-base-300 bg-base-100 px-4 py-3 shadow-2xl">
            <span class="shrink-0 border-r border-base-200 pr-3 text-sm font-semibold text-base-content/80">
                {{ trans_choice('selectedCount', count($selected), ['count' => count($selected)]) }}
            </span>

            <div class="flex flex-wrap items-center gap-1.5">
                {{ $actions }}
            </div>

            <x-button
                class="btn-ghost btn-sm btn-circle"
                icon="o-x-mark"
                :tooltip="__('Clear selection')"
                wire:click="clearSelection" :aria-label="__('Clear selection')" />
        </div>
    </div>

    {{-- Spacer --}}
    <div class="h-24"></div>
@endif
