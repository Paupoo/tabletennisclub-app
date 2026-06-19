@props([
    'icon',
    'label',
    'description' => null,
    'color'       => 'base',
])

@php
    $colorBg   = match ($color) {
        'primary' => 'bg-primary/10',
        'info'    => 'bg-info/10',
        'error'   => 'bg-error/10',
        'warning' => 'bg-warning/10',
        'success' => 'bg-success/10',
        default   => 'bg-base-200',
    };
    $colorIcon = match ($color) {
        'primary' => 'text-primary',
        'info'    => 'text-info',
        'error'   => 'text-error',
        'warning' => 'text-warning-content',
        'success' => 'text-success',
        default   => 'text-base-content/60',
    };
@endphp

<button {{ $attributes->merge(['class' => 'flex w-full items-center gap-4 py-3 hover:bg-base-200 rounded-xl px-3 -mx-3 transition-colors text-left']) }}>
    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $colorBg }}">
        <x-icon name="{{ $icon }}" class="h-5 w-5 {{ $colorIcon }}" />
    </div>
    <div class="min-w-0 flex-1">
        <div class="text-sm font-semibold">{{ $label }}</div>
        @if ($description)
            <div class="text-xs text-base-content/40">{{ $description }}</div>
        @endif
    </div>
    <x-icon name="o-chevron-right" class="h-4 w-4 shrink-0 text-base-content/20" />
</button>
