@props([
    'label' => null,
    'icon' => 'o-ellipsis-vertical',
])

<x-dropdown>
    <x-slot:trigger>
        <x-button {{ $attributes->merge(['class' => 'btn-sm btn-ghost']) }} icon="{{ $icon }}"
            label="{{ $label }}" />
    </x-slot:trigger>

    {{ $slot }}
</x-dropdown>
