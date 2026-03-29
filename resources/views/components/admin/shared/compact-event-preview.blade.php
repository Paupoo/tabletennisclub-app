@props([
'link' => '#',
'location',
'name',
'startDateTime',
'type',
])

@php
$date = \Carbon\Carbon::parse($startDateTime);

$colors = [
'interclub' => 'border-primary',
'tournament' => 'border-secondary',
'training' => 'border-accent',
'meeting' => 'border-info',
'socials' => 'border-neutral',
];

$borderClass = $colors[$type] ?? 'border-gray-300';
@endphp

<a {{ $attributes->merge([
    'class' => "group relative isolate overflow-hidden my-4 flex justify-between items-center border-l-4 $borderClass pl-3 py-2 transition-all hover:rounded-r-lg hover:shadow-sm",
]) }}
    href="{{ $link }}">

    {{-- Fond au survol --}}
    <div
        class="bg-base-200/50 absolute inset-0 z-0 -translate-x-full transition-transform duration-300 ease-out group-hover:translate-x-0">
    </div>

    {{-- Contenu principal (Gauche) --}}
    <div class="relative z-10 flex items-center gap-4">
        <div class="min-w-[45px] text-center">
            <span class="block text-xl font-bold leading-none">{{ $date->format('d') }}</span>
            <span class="text-xs uppercase">{{ __($date->translatedFormat('M')) }}.</span>
        </div>

        <div>
            <p class="text-sm font-semibold leading-tight">{!! $name !!}</p> {{-- {!! !!} pour régler le souci d'apostrophe --}}
            <p class="flex items-center gap-1 text-xs opacity-60">
                <x-icon class="h-3 w-3" name="o-clock" /> {{ $date->format('H:i') }} - <x-icon class="h-3 w-3" name="o-map-pin" /> {{ $location }}
            </p>
        </div>
    </div>

    {{-- Actions (Droite) --}}
    @if (isset($actions))
    <div class="relative z-10 flex items-center gap-2 pr-3">
        {{ $actions }}
    </div>
    @endif
</a>