@props([
    'endTime' => null,
    'link' => '#',
    'location',
    'organizer',
    'remainingSlots',
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

    $borderClass = $colors[$type] ?? 'border-base-300';

    // Un événement sans page de détail reste un simple bloc : pas de lien mort.
    $isLink = filled($link) && $link !== '#';
    $tag = $isLink ? 'a' : 'div';
@endphp

<{{ $tag }} {{ $attributes->merge([
    'class' => "group relative isolate overflow-hidden my-4 flex flex-col gap-2 border-l-4 $borderClass pl-3 py-2 transition-all hover:rounded-r-lg hover:shadow-sm sm:flex-row sm:items-center sm:justify-between sm:gap-4",
]) }}
    @if ($isLink) href="{{ $link }}" @endif>

    {{-- Fond au survol --}}
    <div
        class="bg-base-200/50 absolute inset-0 z-0 -translate-x-full transition-transform duration-300 ease-out group-hover:translate-x-0">
    </div>

    {{-- Contenu principal (Gauche) --}}
    <div class="relative z-10 flex min-w-0 items-center gap-4">
        <div class="min-w-[45px] text-center">
            <span class="block text-xl font-bold leading-none">{{ $date->format('d') }}</span>
            <span class="text-xs uppercase">{{ __($date->translatedFormat('M')) }}.</span>
        </div>

        <div class="min-w-0">
            <p class="text-sm font-semibold leading-tight">{{ $name }}</p>
            <p class="flex flex-wrap items-center gap-1 text-xs opacity-60">
                {{-- On affiche toujours l'heure --}}
                <x-icon class="h-3 w-3" name="o-clock" />
                    {{ $date->format('H:i') }}{{ $endTime ? '–' . $endTime : '' }}

                {{-- On ajoute le lieu s'il existe --}}
                @isset($location)
                    <span class="mx-1">-</span>
                    <x-icon class="h-3 w-3" name="o-map-pin" /> {{ $location }}
                @endisset

                {{-- On ajoute l'organisateur s'il existe --}}
                @isset ($organizer)
                    <span class="mx-1">-</span>
                    <x-icon class="h-3 w-3" name="o-user" /> {{ $organizer }}
                @endisset

                {{-- On ajoute le nombre de place restantes si elles existent --}}
                @isset ($remainingSlots)
                    <span class="mx-1">-</span>
                    <x-icon class="h-3 w-3" name="o-users" /> {{ $remainingSlots }} {{ __('slots left') }}
                @endisset
            </p>
        </div>
    </div>

    {{-- Actions (Droite en desktop, dessous en mobile) --}}
    @if (isset($actions))
        <div class="relative z-10 flex flex-wrap items-center gap-2 pl-16 sm:shrink-0 sm:justify-end sm:pl-0 sm:pr-3">
            {{ $actions }}
        </div>
    @endif
</{{ $tag }}>
