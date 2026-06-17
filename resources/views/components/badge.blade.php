@props([
    'tone'  => 'neutral',
    'solid' => false,
    'size'  => 'sm',
])

@php
    $palette = [
        'primary'   => ['solid' => 'bg-club-blue text-white',         'soft' => 'bg-blue-100 text-blue-700'],
        'secondary' => ['solid' => 'bg-club-yellow text-club-blue',   'soft' => 'bg-amber-100 text-amber-700'],
        'dark'      => ['solid' => 'bg-gray-800 text-white',          'soft' => 'bg-gray-700 text-white'],
        'success'   => ['solid' => 'bg-green-700 text-white',         'soft' => 'bg-green-100 text-green-700'],
        'info'      => ['solid' => 'bg-blue-600 text-white',          'soft' => 'bg-blue-50 text-blue-700'],
        'warning'   => ['solid' => 'bg-amber-500 text-white',         'soft' => 'bg-amber-50 text-amber-700'],
        'danger'    => ['solid' => 'bg-red-600 text-white',           'soft' => 'bg-red-100 text-red-700'],
        'purple'    => ['solid' => 'bg-purple-700 text-white',        'soft' => 'bg-purple-100 text-purple-700'],
        'orange'    => ['solid' => 'bg-orange-600 text-white',        'soft' => 'bg-orange-100 text-orange-700'],
        'neutral'   => ['solid' => 'bg-gray-600 text-white',          'soft' => 'bg-gray-100 text-gray-500'],
    ];

    $variant      = $solid ? 'solid' : 'soft';
    $colorClasses = $palette[$tone][$variant] ?? $palette['neutral'][$variant];

    $sizeClasses = match ($size) {
        'xs'    => 'text-xs px-2 py-0.5',
        default => 'text-xs px-3 py-1',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full font-semibold $sizeClasses $colorClasses"]) }}>
    {{ $slot }}
</span>
