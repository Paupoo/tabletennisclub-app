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
    'locked' => 'bg-orange-100 text-orange-800',
    'pending' => 'bg-green-100 text-green-800',
    'closed' => 'bg-yellow-100 text-yellow-800',
    'cancelled' => 'bg-red-100 text-red-800',
    'success' => 'bg-green-100 text-green-800',
    'warning' => 'bg-orange-100 text-orange-800',
];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . ($classes[$variant] ?? $classes['default'])]) }}>
    {{ $slot }}
</span>