<?php

declare(strict_types=1);

namespace App\View\Components\Admin\Shared;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Component;

/**
 * A single panel inside <x-admin.shared.tabs>.
 *
 * Registers itself into the parent's Alpine tab list (label + icon), then shows
 * its slot when selected. Leave the slot empty to use the tab purely as a filter
 * control. Mirrors maryUI's Tab registration so dynamic, Livewire-morphed tabs
 * stay in sync.
 */
class Tab extends Component
{
    public string $uuid;

    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $label = null,
        public ?string $icon = null,
        public bool $disabled = false,
        public bool $hidden = false,
    ) {
        $this->uuid = 'tab-' . md5(serialize($this)) . $id;
    }

    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <a
                    class="hidden"
                    data-name="{{ $name }}"
                    x-init="
                        const newItem = { name: '{{ $name }}', label: {{ json_encode($tabLabel($label)) }}, disabled: {{ $disabled ? 'true' : 'false' }}, hidden: {{ $hidden ? 'true' : 'false' }} };
                        const index = tabs.findIndex(item => item.name === '{{ $name }}');
                        index !== -1 ? tabs[index] = newItem : tabs.push(newItem);

                        Livewire.hook('morph.removed', ({el}) => {
                            if (el.getAttribute('data-name') == '{{ $name }}') {
                                tabs = tabs.filter(i => i.name !== '{{ $name }}')
                            }
                        })
                    "
                ></a>

                @if (! $slot->isEmpty())
                    <div x-show="selected === '{{ $name }}'" role="tabpanel" {{ $attributes->class('mt-4') }}>
                        {{ $slot }}
                    </div>
                @endif
            HTML;
    }

    /**
     * Build the label markup (icon + text) injected into the parent tab button.
     */
    public function tabLabel(string $label): string
    {
        $fromLabel = $this->label ?: $label;

        if ($this->icon) {
            return Blade::render("
                <x-icon name='{$this->icon}' class='h-4 w-4 shrink-0' />
                <span class='whitespace-nowrap'>{$fromLabel}</span>
            ");
        }

        return Blade::render("<span class='whitespace-nowrap'>{$fromLabel}</span>");
    }
}
