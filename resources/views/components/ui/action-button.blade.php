<?php
// ===========================================
// resources/views/components/ui/action-button.blade.php
// ===========================================
?>
@props(['variant' => 'default', 'icon' => null, 'tooltip' => ''])

@php
$classes = [
    'default' => 'text-gray-400 hover:text-gray-600',
    'primary' => 'text-indigo-600 hover:text-indigo-900',
    'success' => 'text-green-600 hover:text-green-900',
    'warning' => 'text-yellow-600 hover:text-yellow-900',
    'danger' => 'text-red-600 hover:text-red-900',
    'info' => 'text-blue-600 hover:text-blue-900',
];
@endphp

<button 
    {{ $attributes->merge(['class' => ($classes[$variant] ?? $classes['default']) . ' transition-colors duration-150']) }}
    @if($tooltip) title="{{ $tooltip }}" @endif
>
    @if($icon)
        <x-ui.icon name="{{ $icon }}" class="w-5 h-5" />
    @else
        {{ $slot }}
    @endif
</button>