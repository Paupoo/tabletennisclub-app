@props([
    'label' => null,
    'icon' => 'o-ellipsis-vertical',
])

<div x-data="{ open: false }" class="inline-flex items-center">
    {{-- Trigger minimaliste --}}
    <button
        x-ref="trigger"
        @click="open = !open"
        type="button"
        {{ $attributes->merge(['class' => 'p-1 text-gray-400 hover:text-main-500 rounded-full hover:bg-base-200 transition-all']) }}
    >
        <x-icon :name="$icon" class="w-4 h-4" />
    </button>

    {{-- Le Menu téléporté --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            @click.outside="open = false"
            x-anchor.bottom-end.offset.2="$refs.trigger"
            x-transition.opacity
            {{-- Ici, on utilise les classes de MaryUI pour le look 'dropdown' --}}
            class="absolute z-[9999] shadow-lg border border-base-200 bg-base-100 rounded-lg min-w-[150px]"
        >
            {{-- On réutilise x-menu pour que x-menu-item retrouve son style (hover, padding, etc) --}}
            <x-menu dynamic class="p-1">
                <div @click="open = false">
                    {{ $slot }}
                </div>
            </x-menu>
        </div>
    </template>
</div>