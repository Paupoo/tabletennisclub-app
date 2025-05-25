<?php
// ===========================================
// resources/views/components/ui/badge.blade.php
// ===========================================
?>
@props(['variant' => 'default'])

@php
$classes = [
    'default' => 'bg-gray-100 text-gray-800',
    'draft' => 'bg-gray-100 text-gray-800',
    'open' => 'bg-blue-100 text-blue-800',
    'pending' => 'bg-yellow-100 text-yellow-800',
    'closed' => 'bg-green-100 text-green-800',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . ($classes[$variant] ?? $classes['default'])]) }}>
    {{ $slot }}
</span>