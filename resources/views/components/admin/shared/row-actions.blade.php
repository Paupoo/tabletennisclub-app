@props([])

<div {{ $attributes->merge(['class' => 'flex items-center gap-1']) }}>
    {{ $slot }}
</div>
