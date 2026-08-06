{{-- resources/views/components/info-alert.blade.php --}}
@props(['icon' => 'o-information-circle'])

<div {{ $attributes->merge(['class' => 'bg-info/5 border-info/10 flex gap-4 rounded-xl border p-4']) }}>
    <x-icon :name="$icon" class="text-info h-6 w-6 shrink-0" />
    {{-- max-w-prose : à 1440px une explication court sur toute la largeur, soit
    ~130 caractères par ligne. L'œil perd la ligne au retour au-delà de 90. --}}
    <p class="max-w-prose text-xs italic leading-relaxed">
        {{ $slot }}
    </p>
</div>
