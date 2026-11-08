{{--
    Une section d'une phase, résumée : son chiffre, son alerte, son bouton.
    Le bouton ouvre la section en plein écran.

    Paramètres : $title, $value (chiffre ou texte court), $caption (?string),
    $alert (?string), $button (libellé), $wire (action Livewire), $key (data-).
--}}
<div class="flex min-w-0 flex-col gap-1.5 rounded-xl border border-base-300 bg-base-100 p-4" data-summary-card="{{ $key }}">
    <p class="text-sm font-bold">{{ $title }}</p>
    <p class="flex flex-wrap items-baseline gap-x-1.5">
        <span class="text-2xl font-bold tabular-nums">{{ $value }}</span>
        @if ($caption ?? null)
            <span class="text-sm text-base-content/70">{{ $caption }}</span>
        @endif
    </p>
    @if ($alert ?? null)
        <p class="text-sm font-semibold text-warning-content">{{ $alert }}</p>
    @endif
    <div class="mt-auto pt-1">
        <x-button :label="$button" class="btn-outline btn-sm" :wire:click="$wire" />
    </div>
</div>
