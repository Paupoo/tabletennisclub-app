    @aware(['activateByRoute' => false, 'activeBgColor' => 'bg-base-300'])

    <li @class(['menu-disabled' => $disabled])>
        <a
            {{
                $attributes->class([
                    "my-0.5 py-1.5 px-4 hover:text-inherit whitespace-nowrap",
                    "mary-active-menu $activeBgColor" => ($active || ($activateByRoute && $routeMatches()))
                ])
            }}

            @if($getHref())
                href="{{ $getHref() }}"

                @if($external)
                    target="_blank"
                @endif

                @if(!$external && !$noWireNavigate)
                    {{ $attributes->wire('navigate')->value() ? $attributes->wire('navigate') : 'wire:navigate' }}
                @endif
            @endif

            @if($spinner)
                wire:target="{{ $spinnerTarget() }}"
                wire:loading.attr="disabled"
            @endif
        >
            {{-- SPINNER --}}
            @if($spinner)
                <span wire:loading wire:target="{{ $spinnerTarget() }}" class="loading loading-spinner loading-xs w-5 h-5 @if($icon) my-1 @endif"></span>
            @endif

            @if($icon)
                <span class="block py-0.5" @if($spinner) wire:loading.class="hidden" wire:target="{{ $spinnerTarget() }}" @endif>
                    <x-mary-icon :name="$icon" @class(['mb-0.5', $iconClasses]) />
                </span>
            @endif

            @if($title || $slot->isNotEmpty())
            <span class="mary-hideable whitespace-nowrap truncate">
                @if($title)
                    {{ $title }}

                    @if($badge)
                        <span class="badge badge-sm {{ $badgeClasses }}">{{ $badge }}</span>
                    @endif
                @else
                    {{ $slot }}
                @endif
            </span>
            @endif
        </a>
    </li>