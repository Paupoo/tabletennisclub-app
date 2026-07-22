<?php

declare(strict_types=1);

namespace App\View\Components\Admin\Shared;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Folder-style tabs for the admin back office.
 *
 * A restyled fork of maryUI's tabs: the active tab carries a solid fill and
 * hairline border so it reads unmistakably as the selected control, sitting
 * flush on the panel (or table card) below. Panels stay client-side (Alpine
 * x-show), so every panel remains in the DOM.
 *
 * Two usages:
 *  - With panels: declare a child <x-admin.shared.tab> per panel.
 *  - As a filter control: bind `wire:model.live` to a status property and leave
 *    the tabs empty; the filtered content lives outside the component.
 *
 * Bind the selection with `wire:model` (entangled) or force one with `selected`.
 */
class Tabs extends Component
{
    public string $uuid;

    public function __construct(
        public ?string $id = null,
        public ?string $selected = null,
    ) {
        $this->uuid = 'tabs-' . md5(serialize($this)) . $id;
    }

    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <div
                    x-data="{
                        tabs: [],
                        selected:
                            @if($selected)
                                '{{ $selected }}'
                            @else
                                @entangle($attributes->wire('model'))
                            @endif
                    }"
                    class="w-full"
                >
                    {{-- Tab labels --}}
                    <div role="tablist" class="flex gap-1.5 overflow-x-auto px-1">
                        <template x-for="tab in tabs" :key="tab.name">
                            <button
                                type="button"
                                role="tab"
                                x-html="tab.label"
                                x-init="if (typeof tab == 'undefined') $el.remove()"
                                @click="tab.disabled ? null : selected = tab.name"
                                :aria-selected="selected === tab.name"
                                :class="{
                                    'border-base-300 bg-base-100 text-primary': selected === tab.name,
                                    'border-transparent text-base-content/50 hover:bg-base-content/5 hover:text-base-content': selected !== tab.name,
                                    'hidden': tab.hidden,
                                    'cursor-not-allowed opacity-50': tab.disabled,
                                }"
                                class="flex shrink-0 -mb-px cursor-pointer items-center gap-2 whitespace-nowrap rounded-t-xl border border-b-0 px-4 py-2.5 text-sm font-semibold transition-colors"
                            ></button>
                        </template>
                    </div>

                    {{-- Panels --}}
                    <div {{ $attributes->except(['wire:model', 'wire:model.live'])->class(['block']) }}>
                        {{ $slot }}
                    </div>
                </div>
            HTML;
    }
}
