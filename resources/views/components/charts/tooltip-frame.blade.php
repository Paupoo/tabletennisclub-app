@props(['interactive' => true])

{{--
    The frame every chart sits in, and its one tooltip.

    A mark calls `show($el, text)` on hover and on keyboard focus: the tooltip
    anchors on the mark rather than on the pointer, so focus and hover read the
    same. The text goes in through x-text — labels are data, never HTML. The
    tooltip only repeats what the legend, the labels and the tables already
    say: it enhances, it never gates.

    Without `interactive` (a PDF, a print) the frame is a plain block: no
    Alpine, no tooltip.
--}}
@if ($interactive)
    <div data-chart {{ $attributes->class('relative') }}
        x-data="{
            tip: '', left: 0, top: 0, open: false,
            show(el, text) {
                const frame = this.$root.getBoundingClientRect();
                const mark = el.getBoundingClientRect();
                this.tip = text;
                this.left = Math.min(Math.max(mark.left - frame.left + mark.width / 2, 80), frame.width - 80);
                this.top = mark.top - frame.top;
                this.open = true;
            },
            hide() { this.open = false; },
        }"
        @mouseleave="hide()">
        {{ $slot }}
        <div x-show="open" x-cloak data-print-hide role="tooltip"
            class="pointer-events-none absolute z-10 max-w-64 -translate-x-1/2 -translate-y-full rounded-lg border border-base-300 bg-base-100 px-3 py-2 text-xs font-semibold shadow-md"
            :style="`left: ${left}px; top: ${top - 6}px`" x-text="tip"></div>
    </div>
@else
    <div data-chart {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
