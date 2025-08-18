{{-- resources/views/components/placeholder-pattern.blade.php --}}
@php
    $patternId = 'placeholder-pattern-' . uniqid();
@endphp

<svg 
    {{ $attributes->merge(['class' => 'pointer-events-none']) }} 
    aria-hidden="true"
    xmlns="http://www.w3.org/2000/svg"
>
    <defs>
        <pattern 
            id="{{ $patternId }}" 
            patternUnits="userSpaceOnUse" 
            width="24" 
            height="24"
        >
            {{-- Grille de base --}}
            <path 
                d="M0 24V0h24v24z" 
                fill="none" 
                stroke="currentColor" 
                stroke-width="0.5"
                opacity="0.1"
            />
            {{-- Lignes diagonales pour créer un motif losange --}}
            <path 
                d="M0 0l24 24M0 24L24 0" 
                stroke="currentColor" 
                stroke-width="0.5"
                opacity="0.08"
            />
            {{-- Points centraux --}}
            <circle 
                cx="12" 
                cy="12" 
                r="1" 
                fill="currentColor" 
                opacity="0.15"
            />
        </pattern>
    </defs>
    <rect 
        width="100%" 
        height="100%" 
        fill="url(#{{ $patternId }})" 
    />
</svg>