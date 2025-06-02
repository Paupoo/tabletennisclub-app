@props([
    'active',
    'iconName' => 'details',
    ])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-indigo-100 hover:bg-indigo-200'
            : 'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-gray-700 bg-white border border-gray-300 hover:bg-gray-200'
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    <x-ui.icon name="{{ $iconName }}" class="mr-2" />
    {{ $slot }}
</a>